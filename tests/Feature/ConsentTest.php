<?php

namespace Tests\Feature;

use App\Consent\ConsentAction;
use App\Consent\OptOut;
use App\Enums\IntegrationType;
use App\Models\ConsentRecord;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\LeadIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Integration $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2030-03-12 12:00', 'Asia/Kolkata')); // a Tuesday, inside working hours
        $this->admin = $this->registerOrganization('Sunrise Realty');
        $this->whatsapp = $this->connectWhatsApp($this->admin->organization);
    }

    private function template(): WhatsAppTemplate
    {
        if ($existing = WhatsAppTemplate::withoutGlobalScopes()->where('name', 'offer')->first()) {
            return $existing;
        }

        $template = WhatsAppTemplate::query()->make(['name' => 'offer', 'language' => 'en', 'status' => 'APPROVED', 'body' => 'Hi {{1}}, new offer!', 'variables' => 1]);
        $template->organization_id = $this->admin->organization_id;
        $template->save();

        return $template;
    }

    private function says(string $text, string $id): void
    {
        $this->whatsAppWebhook($this->whatsapp, [['from' => '919876543210', 'id' => $id, 'type' => 'text', 'text' => ['body' => $text]]], 'Priya Sharma')->assertOk();
    }

    public function test_only_a_whole_message_stop_or_start_counts(): void
    {
        foreach (['STOP', ' stop. ', 'Unsubscribe!', 'band karo', 'बंद करो', 'Stop all'] as $text) {
            $this->assertSame(ConsentAction::Withdrawn, OptOut::intentOf($text), $text);
        }
        foreach (['START', 'start 🙂'] as $text) {
            $this->assertSame(ConsentAction::Given, OptOut::intentOf($text), $text);
        }
        foreach (['Stop by the office tomorrow?', 'Please dont stop', 'started'] as $text) {
            $this->assertNull(OptOut::intentOf($text), $text);
        }
    }

    public function test_a_lead_who_replies_stop_is_told_and_nothing_automatic_contacts_them(): void
    {
        $this->says('Hi, is the flat available?', 'wamid.1');
        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Enquired via WhatsApp', ConsentRecord::withoutGlobalScopes()->sole()->how, 'reaching out is consent');

        $this->actingAs($this->admin);
        $sequence = Sequence::create(['name' => 'Nurture', 'is_active' => true, 'stop_on_reply' => false]);
        $sequence->replaceSteps([['day' => 2, 'action' => 'whatsapp_template', 'whatsapp_template_id' => $this->template()->id, 'note' => null]]);
        $this->post("/leads/{$lead->id}/sequence", ['sequence_id' => $sequence->id]);

        $this->says('STOP', 'wamid.2');

        $lead->refresh();
        $this->assertNotNull($lead->opted_out_at);
        $this->assertSame("You won't get any more messages from Sunrise Realty. If you change your mind, reply START.", WhatsAppMessage::withoutGlobalScopes()->where('direction', 'out')->latest('id')->first()->body);
        $this->assertNull($lead->activeEnrollment()->first(), 'their sequence stopped');
        $this->assertSame(['withdrawn', 'given'], ConsentRecord::withoutGlobalScopes()->orderByDesc('id')->pluck('action')->map->value->all());

        $sent = WhatsAppMessage::withoutGlobalScopes()->where('direction', 'out')->count();
        $this->post("/leads/{$lead->id}/whatsapp", ['template_id' => $this->template()->id, 'parameters' => ['Priya']])
            ->assertSessionHasErrors(['body' => 'Priya asked not to get messages, so only replies to their own messages can be sent.']);
        $this->post("/leads/{$lead->id}/whatsapp", ['body' => 'Sorry to see you go!'])->assertSessionHasNoErrors();
        $this->assertSame($sent + 1, WhatsAppMessage::withoutGlobalScopes()->where('direction', 'out')->count(), 'a person may still reply in the window');

        $this->get("/leads/{$lead->id}?tab=whatsapp")->assertSee('No messages')->assertSee('asked not to get messages')->assertDontSee('Choose a template');

        $this->says('start', 'wamid.3');
        $this->assertNull($lead->fresh()->opted_out_at);
        $this->assertStringContainsString('Welcome back!', WhatsAppMessage::withoutGlobalScopes()->where('direction', 'out')->latest('id')->first()->body);
    }

    public function test_win_back_and_quiet_lead_nudges_skip_opted_out_leads(): void
    {
        $template = $this->template();
        $this->admin->organization->forceFill(['autopilot' => ['win_back' => true, 'win_back_template_id' => $template->id, 'reengage' => true, 'reengage_days' => 14, 'reengage_template_id' => $template->id]])->save();
        $lost = LeadStatus::query()->withoutGlobalScopes()->where('organization_id', $this->admin->organization_id)->where('name', 'Lost')->sole();
        $price = $this->admin->organization->lostReasons()->where('name', 'Price too high')->sole();

        $lostLead = fn (array $extra) => Lead::factory()->for($this->admin->organization)->create($extra + ['status_id' => $lost->id, 'lost_reason_id' => $price->id, 'closed_at' => now()->subDays(40)]);
        $stopped = $lostLead(['opted_out_at' => now()]);
        $contactable = $lostLead([]);
        $quiet = Lead::factory()->for($this->admin->organization)->create(['created_at' => now()->subDays(20), 'opted_out_at' => now()]);

        $this->artisan('crm:win-back')->expectsOutput('Won back 1 leads.');
        $this->artisan('crm:autopilot');

        $this->assertSame([$contactable->id], WhatsAppMessage::withoutGlobalScopes()->pluck('lead_id')->all(), 'only the lead who did not say stop');
        $this->assertNull($stopped->fresh()->win_back_at);
        $this->assertNull($quiet->fresh()->reengaged_at);
    }

    public function test_team_members_can_stop_and_allow_messages_from_the_lead_page(): void
    {
        $agent = $this->addAgent($this->admin->organization);
        $lead = Lead::factory()->for($this->admin->organization)->create(['assigned_to' => $agent->id]);

        $this->actingAs($agent)->get("/leads/{$lead->id}")->assertSee('Allowed')->assertSee('Added by your team (no consent recorded)');
        $this->post("/leads/{$lead->id}/consent", ['messages' => 'stop'])->assertSessionHas('status');
        $this->assertNotNull($lead->fresh()->opted_out_at);
        $this->get("/leads/{$lead->id}")->assertSee('Stopped')->assertSee('marked by '.$agent->name);

        $this->post("/leads/{$lead->id}/consent", ['messages' => 'allow']);
        $this->assertNull($lead->fresh()->opted_out_at);

        $other = Lead::factory()->for($this->admin->organization)->create(['assigned_to' => $this->admin->id]);
        $this->post("/leads/{$other->id}/consent", ['messages' => 'stop'])->assertForbidden();
    }

    public function test_admins_can_download_and_erase_a_leads_data(): void
    {
        $this->says('Hi, budget is 80 lakh', 'wamid.1');
        $lead = Lead::withoutGlobalScopes()->sole();
        $this->actingAs($this->admin);
        $this->post("/leads/{$lead->id}/activities", ['status_id' => $lead->status_id, 'note' => 'Lives near Baner, 2 kids']);

        $download = $this->get("/leads/{$lead->id}/data")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="priya-sharma-data.json"');
        $this->assertSame('Priya Sharma', $download->json('details.name'));
        $this->assertSame('Enquired via WhatsApp', $download->json('consent.0.how'));
        $this->assertSame('Hi, budget is 80 lakh', $download->json('whatsapp.0.text'));

        $this->post("/leads/{$lead->id}/erase")->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame(['Erased lead', "erased-{$lead->id}"], [$lead->name, $lead->phone]);
        $this->assertNotNull($lead->erased_at);
        $this->assertSame(0, WhatsAppMessage::withoutGlobalScopes()->count());
        $this->assertNull($lead->activities()->whereNotNull('note')->where('note', 'like', '%Baner%')->first());
        $this->get("/leads/{$lead->id}")->assertSee('Data erased')->assertDontSee('Lives near Baner');
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.erased']);

        $agent = $this->addAgent($this->admin->organization);
        $mine = Lead::factory()->for($this->admin->organization)->create(['assigned_to' => $agent->id]);
        $this->actingAs($agent)->get("/leads/{$mine->id}/data")->assertForbidden();
        $this->post("/leads/{$mine->id}/erase")->assertForbidden();
    }

    public function test_closed_leads_are_erased_after_the_retention_period(): void
    {
        $this->actingAs($this->admin);
        $this->get('/settings/privacy')->assertOk()->assertSee('Privacy & consent')->assertSee('band karo');
        $this->put('/settings/privacy', ['retention_months' => 7])->assertSessionHasErrors('retention_months');
        $this->put('/settings/privacy', ['retention_months' => 12])->assertSessionHas('status');

        $won = LeadStatus::query()->where('name', 'Won')->sole();
        $old = Lead::factory()->for($this->admin->organization)->create(['status_id' => $won->id, 'closed_at' => now()->subMonths(13)]);
        $recent = Lead::factory()->for($this->admin->organization)->create(['status_id' => $won->id, 'closed_at' => now()->subMonths(11)]);
        $open = Lead::factory()->for($this->admin->organization)->create(['created_at' => now()->subYears(2)]);

        $this->artisan('crm:retention')->expectsOutput('Erased 1 leads.');
        $this->artisan('crm:retention')->expectsOutput('Erased 0 leads.');
        $this->assertNotNull($old->fresh()->erased_at);
        $this->assertNull($recent->fresh()->erased_at);
        $this->assertNull($open->fresh()->erased_at);
        $this->assertSame((float) $old->value, (float) $old->fresh()->value, 'reports keep the numbers');
    }

    public function test_forms_and_ads_record_consent_and_the_form_says_how_people_will_be_contacted(): void
    {
        $lead = app(LeadIntake::class)->capture($this->admin->organization, ['name' => 'Ravi', 'phone' => '+919811111111'], 'Facebook')->lead;
        $this->assertSame('Enquired via Facebook', $lead->consentRecords()->withoutGlobalScopes()->sole()->how);

        $form = new Integration(['type' => IntegrationType::WebForm, 'settings' => ['title' => 'Book a visit']]);
        $form->organization_id = $this->admin->organization_id;
        $form->save();
        $this->get("/f/{$form->webhook_key}")->assertSee('Sunrise Realty may contact you about your enquiry by phone, WhatsApp or email. Reply STOP');
    }
}
