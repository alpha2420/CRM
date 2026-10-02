<?php

namespace Tests\Feature;

use App\Ai\LeadAssistant;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Integration $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        config(['services.gemini.api_key' => 'gem-key', 'services.gemini.model' => 'gemini-3.8-flash', 'services.gemini.fallback_model' => null]);
        $this->admin = $this->registerOrganization();
        $this->whatsapp = $this->connectWhatsApp($this->admin->organization);
    }

    /**
     * WhatsApp's media API, its file server and Gemini, all faked.
     *
     * @param  array<string, string>  $transcript
     */
    private function fakeServices(array $transcript = ['transcript' => 'Bhaiya 2BHK ka rate kya hai? Kal site visit ho sakti hai?', 'summary' => 'Wants the 2BHK price and a site visit tomorrow.'], int $size = 2048): void
    {
        Http::fake([
            'graph.facebook.com/*/MEDIA_AUDIO' => Http::response(['url' => 'https://lookaside.fbsbx.com/audio', 'mime_type' => 'audio/ogg; codecs=opus', 'file_size' => $size]),
            'graph.facebook.com/*/MEDIA_PHOTO' => Http::response(['url' => 'https://lookaside.fbsbx.com/photo', 'mime_type' => 'image/jpeg', 'file_size' => 900]),
            'graph.facebook.com/*/MEDIA_PDF' => Http::response(['url' => 'https://lookaside.fbsbx.com/pdf', 'mime_type' => 'application/pdf', 'file_size' => 900]),
            'lookaside.fbsbx.com/audio' => Http::response('OGG-AUDIO-BYTES'),
            'lookaside.fbsbx.com/photo' => Http::response('JPEG-BYTES'),
            'lookaside.fbsbx.com/pdf' => Http::response('%PDF-1.7'),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($transcript)]]]]]]),
        ]);
    }

    private function voiceNote(string $id = 'wamid.v1'): array
    {
        return ['from' => '919876543210', 'id' => $id, 'type' => 'audio', 'audio' => ['id' => 'MEDIA_AUDIO', 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true]];
    }

    public function test_a_voice_note_is_saved_written_down_and_summarised(): void
    {
        $this->fakeServices();

        $this->whatsAppWebhook($this->whatsapp, [$this->voiceNote()], 'Priya')->assertOk();

        $message = WhatsAppMessage::withoutGlobalScopes()->sole();
        $this->assertSame('audio', $message->type);
        $this->assertSame('audio/ogg', $message->media_mime);
        $this->assertSame('Bhaiya 2BHK ka rate kya hai? Kal site visit ho sakti hai?', $message->transcript);
        Storage::disk('local')->assertExists($message->media_path);
        $this->assertSame(1, $this->admin->organization->fresh()->ai_usage_count, 'a transcript uses one AI analysis');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage')
            && $request['contents'][0]['parts'][0]['inlineData'] === ['mimeType' => 'audio/ogg', 'data' => base64_encode('OGG-AUDIO-BYTES')]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://lookaside.fbsbx.com/audio' && $request->hasHeader('Authorization', 'Bearer token-123'));

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->actingAs($this->admin)->get("/leads/{$lead->id}?tab=whatsapp")
            ->assertSee('What they said')
            ->assertSee('Wants the 2BHK price and a site visit tomorrow.')
            ->assertSee(route('messages.file', $message), false);
        $this->get('/inbox')->assertSee('🎤 Voice note: Bhaiya 2BHK ka rate kya hai?');
        $this->assertStringContainsString('Lead: 🎤 Voice note: Bhaiya 2BHK', app(LeadAssistant::class)->prompt($lead));

        $this->get(route('messages.file', $message))->assertOk()
            ->assertHeader('Content-Type', 'audio/ogg')
            ->assertHeader('Content-Disposition', 'inline; filename=voice-note-'.$message->id.'.ogg');
    }

    public function test_photos_and_documents_are_shown_with_their_captions_and_documents_only_download(): void
    {
        $this->fakeServices();

        $this->whatsAppWebhook($this->whatsapp, [
            ['from' => '919876543210', 'id' => 'wamid.p1', 'type' => 'image', 'image' => ['id' => 'MEDIA_PHOTO', 'mime_type' => 'image/jpeg', 'caption' => 'This plot?']],
            ['from' => '919876543210', 'id' => 'wamid.d1', 'type' => 'document', 'document' => ['id' => 'MEDIA_PDF', 'mime_type' => 'application/pdf', 'filename' => 'Aadhaar.pdf']],
        ])->assertOk();

        [$photo, $pdf] = WhatsAppMessage::withoutGlobalScopes()->orderBy('id')->get()->all();
        $this->assertSame(['This plot?', 'This plot?'], [$photo->body, $photo->caption()]);
        $this->assertSame(['Aadhaar.pdf', null], [$pdf->body, $pdf->caption()], 'a file name is not a caption');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage'));

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->actingAs($this->admin)->get("/leads/{$lead->id}?tab=whatsapp")->assertSee('<img src="'.route('messages.file', $photo).'"', false)->assertSee('Aadhaar.pdf');
        $this->get(route('messages.file', $pdf))->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Disposition', 'attachment; filename=Aadhaar.pdf');
    }

    public function test_voice_notes_are_only_written_down_when_switched_on_and_within_the_ai_allowance(): void
    {
        $this->fakeServices();
        $this->admin->organization->forceFill(['autopilot' => ['voice_notes' => false]])->save();
        $this->whatsAppWebhook($this->whatsapp, [$this->voiceNote('wamid.v1')])->assertOk();

        $this->admin->organization->forceFill(['autopilot' => ['voice_notes' => true], 'ai_usage_month' => now()->format('Y-m'), 'ai_usage_count' => config('crm.ai_monthly_limit')])->save();
        $this->whatsAppWebhook($this->whatsapp, [$this->voiceNote('wamid.v2')])->assertOk();

        $this->assertSame(2, WhatsAppMessage::withoutGlobalScopes()->whereNotNull('media_path')->whereNull('transcript')->count(), 'saved and playable, but not written down');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage'));

        $this->actingAs($this->admin)->get('/settings/autopilot')->assertSee('Write down voice notes');
        config(['services.gemini.api_key' => '']);
        $this->get('/settings/autopilot')->assertSee('Needs a Gemini API key on this server');
    }

    public function test_a_file_that_is_too_large_or_gone_says_why(): void
    {
        $this->fakeServices(size: 40 * 1024 * 1024);
        $this->whatsAppWebhook($this->whatsapp, [$this->voiceNote()])->assertOk();

        $message = WhatsAppMessage::withoutGlobalScopes()->sole();
        $this->assertNull($message->media_path);
        $this->assertStringContainsString('larger than 25 MB', $message->error);
        $this->actingAs($this->admin)->get(route('messages.file', $message))->assertNotFound();
        $this->get('/leads/'.$message->lead_id.'?tab=whatsapp')->assertSee('larger than 25 MB')->assertDontSee('downloading…');
    }

    public function test_files_are_private_to_the_workspace_and_removed_with_the_lead(): void
    {
        $this->fakeServices();
        $this->whatsAppWebhook($this->whatsapp, [$this->voiceNote()])->assertOk();
        $message = WhatsAppMessage::withoutGlobalScopes()->sole();

        $this->get(route('messages.file', $message))->assertRedirect('/login');
        $outsider = $this->registerOrganization('Other');
        $this->actingAs($outsider)->get(route('messages.file', $message))->assertNotFound();

        $this->actingAs($this->admin)->delete('/leads/'.$message->lead_id);
        Storage::disk('local')->assertMissing($message->media_path);
    }

    public function test_locations_contacts_and_reactions_read_as_sentences(): void
    {
        $this->whatsAppWebhook($this->whatsapp, [
            ['from' => '919876543210', 'id' => 'wamid.l1', 'type' => 'location', 'location' => ['latitude' => 18.5204, 'longitude' => 73.8567, 'name' => 'Baner']],
            ['from' => '919876543210', 'id' => 'wamid.c1', 'type' => 'contacts', 'contacts' => [['name' => ['formatted_name' => 'Ravi Kumar'], 'phones' => [['phone' => '+91 98200 00000']]]]],
            ['from' => '919876543210', 'id' => 'wamid.r1', 'type' => 'reaction', 'reaction' => ['message_id' => 'x', 'emoji' => '👍']],
        ])->assertOk();

        $this->assertSame([
            '📍 Shared a location: Baner https://www.google.com/maps?q=18.5204,73.8567',
            'Shared a contact: Ravi Kumar +91 98200 00000',
            'Reacted 👍',
        ], WhatsAppMessage::withoutGlobalScopes()->orderBy('id')->pluck('body')->all());
    }
}
