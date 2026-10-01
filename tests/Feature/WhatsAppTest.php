<?php

namespace Tests\Feature;

use App\Enums\IntegrationType;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Notifications\WhatsAppReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Integration $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->registerOrganization();
        $this->whatsapp = $this->connect($this->admin->organization);
    }

    public function test_meta_webhook_handshake_checks_the_verify_token(): void
    {
        $url = "/api/webhooks/meta/{$this->whatsapp->webhook_key}";

        $this->get($url.'?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get($url.'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
    }

    public function test_a_message_from_a_new_number_creates_a_lead_and_notifies_its_agent(): void
    {
        Notification::fake();
        $agent = $this->addAgent($this->admin->organization);

        $this->webhook($this->inbound('919876543210', 'Hi, is the 2BHK available?', 'wamid.1', 'Priya'))->assertOk();

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Priya', $lead->name);
        $this->assertSame('+919876543210', $lead->phone);
        $this->assertSame('WhatsApp', $lead->source()->withoutGlobalScopes()->first()->name);
        $this->assertSame($agent->id, $lead->assigned_to);
        $this->assertTrue($lead->whatsappWindowOpen());
        $this->assertSame('Hi, is the 2BHK available?', WhatsAppMessage::withoutGlobalScopes()->sole()->body);
        Notification::assertSentTo($agent, WhatsAppReceivedNotification::class);
    }

    public function test_messages_are_matched_to_leads_saved_without_country_code_and_never_duplicated(): void
    {
        $lead = Lead::factory()->for($this->admin->organization)->create(['phone' => '9876543210']);

        $this->webhook($this->inbound('919876543210', 'Hello', 'wamid.2'))->assertOk();
        $this->webhook($this->inbound('919876543210', 'Hello', 'wamid.2'))->assertOk();

        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
        $this->assertSame(1, $lead->whatsappMessages()->count());
    }

    public function test_unsigned_webhooks_are_rejected(): void
    {
        $this->postJson("/api/webhooks/meta/{$this->whatsapp->webhook_key}", $this->inbound('919876543210', 'x', 'wamid.3'))
            ->assertForbidden();

        $this->assertSame(0, WhatsAppMessage::withoutGlobalScopes()->count());
    }

    public function test_agents_reply_inside_the_24_hour_window(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out1']]])]);
        $lead = Lead::factory()->for($this->admin->organization)->create(['phone' => '+919876543210']);
        $lead->forceFill(['last_inbound_at' => now()->subHours(2)])->save();

        $this->actingAs($this->admin)
            ->post("/leads/{$lead->id}/whatsapp", ['body' => 'Yes, it is available!'])
            ->assertRedirect("/leads/{$lead->id}?tab=whatsapp");

        $message = WhatsAppMessage::sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.out1', $message->wa_message_id);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v25.0/PHONE_ID/messages')
            && $request['to'] === '919876543210'
            && $request['text']['body'] === 'Yes, it is available!'
            && $request->hasHeader('Authorization', 'Bearer token-123'));
    }

    public function test_outside_the_window_only_templates_can_be_sent(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.t1']]])]);
        $lead = Lead::factory()->for($this->admin->organization)->create(['name' => 'Rahul Verma', 'phone' => '9876543210']);
        $template = $this->template('welcome', 'Hi {{1}}, thanks for contacting {{2}}!', 2);

        $this->actingAs($this->admin)->post("/leads/{$lead->id}/whatsapp", ['body' => 'Hello'])->assertSessionHasErrors('body');
        $this->assertSame(0, WhatsAppMessage::count());

        $this->get("/leads/{$lead->id}?tab=whatsapp&template={$template->id}")->assertOk()->assertSee('value="Rahul"', false);
        $this->post("/leads/{$lead->id}/whatsapp", ['template_id' => $template->id, 'parameters' => ['Rahul', 'Acme']])->assertRedirect();

        $this->assertSame('Hi Rahul, thanks for contacting Acme!', WhatsAppMessage::sole()->body);
        Http::assertSent(fn ($request) => $request['type'] === 'template'
            && $request['template']['name'] === 'welcome'
            && $request['template']['language']['code'] === 'en'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'Rahul');
    }

    public function test_delivery_statuses_only_move_forward_and_failures_are_recorded(): void
    {
        $lead = Lead::factory()->for($this->admin->organization)->create();
        $message = $lead->whatsappMessages()->make(['direction' => 'out', 'phone' => '91', 'status' => 'sent', 'wa_message_id' => 'wamid.s1', 'body' => 'x']);
        $message->organization_id = $lead->organization_id;
        $message->save();

        $this->webhook($this->statusPayload('wamid.s1', 'read'));
        $this->webhook($this->statusPayload('wamid.s1', 'delivered'));
        $this->assertSame('read', $message->fresh()->status);

        $this->webhook($this->statusPayload('wamid.s1', 'failed', [['title' => 'Re-engagement message']]));
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('Re-engagement message', $message->fresh()->error);
    }

    public function test_rejected_sends_are_marked_failed_with_the_reason(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Recipient not on WhatsApp']], 400)]);
        $lead = Lead::factory()->for($this->admin->organization)->create();
        $lead->forceFill(['last_inbound_at' => now()])->save();

        $this->actingAs($this->admin)->post("/leads/{$lead->id}/whatsapp", ['body' => 'Hi']);

        $this->assertSame('failed', WhatsAppMessage::sole()->status);
        $this->assertSame('Recipient not on WhatsApp', WhatsAppMessage::sole()->error);
    }

    public function test_templates_are_synced_from_whatsapp(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['name' => 'welcome', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}} from {{2}}']]],
            ['name' => 'offer', 'language' => 'hi', 'status' => 'PENDING', 'category' => 'MARKETING', 'components' => [['type' => 'BODY', 'text' => 'Offer!']]],
        ]])]);

        $this->actingAs($this->admin)->post('/settings/integrations/whatsapp/templates')->assertSessionHas('status', 'Synced 2 templates from WhatsApp.');

        $welcome = WhatsAppTemplate::where('name', 'welcome')->sole();
        $this->assertSame(2, $welcome->variables);
        $this->assertTrue($welcome->isApproved());
    }

    public function test_the_inbox_lists_conversations_with_unread_counts_for_the_right_people(): void
    {
        $agent = $this->addAgent($this->admin->organization);
        $other = $this->addAgent($this->admin->organization);
        $this->webhook($this->inbound('919000000001', 'Price please', 'wamid.i1', 'Asha'));
        $lead = Lead::withoutGlobalScopes()->sole();
        $lead->forceFill(['assigned_to' => $agent->id])->save();

        $this->actingAs($agent)->get('/inbox')->assertOk()->assertSee('Asha')->assertSee('Price please');
        $this->actingAs($other)->get('/inbox')->assertOk()->assertDontSee('Asha');

        $this->actingAs($agent)->get("/leads/{$lead->id}?tab=whatsapp")->assertOk();
        $this->assertNotNull(WhatsAppMessage::withoutGlobalScopes()->sole()->read_at);
    }

    public function test_whatsapp_needs_a_plan_that_includes_it(): void
    {
        $this->admin->organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save();

        $this->actingAs($this->admin->fresh())->get('/inbox')->assertRedirect('/settings/billing');
        $this->webhook($this->inbound('919876543210', 'Hi', 'wamid.p1'))->assertOk();
        $this->assertSame(0, WhatsAppMessage::withoutGlobalScopes()->count());
    }

    private function connect(Organization $organization): Integration
    {
        $integration = new Integration(['type' => IntegrationType::WhatsApp, 'settings' => [
            'phone_number_id' => 'PHONE_ID', 'waba_id' => 'WABA_ID', 'access_token' => 'token-123',
            'app_secret' => 'app-secret', 'verify_token' => 'verify-me', 'default_country_code' => '91',
        ]]);
        $integration->organization_id = $organization->id;
        $integration->save();

        return $integration;
    }

    private function template(string $name, string $body, int $variables): WhatsAppTemplate
    {
        $template = new WhatsAppTemplate(['name' => $name, 'language' => 'en', 'status' => 'APPROVED', 'body' => $body, 'variables' => $variables]);
        $template->organization_id = $this->admin->organization_id;
        $template->save();

        return $template;
    }

    private function webhook(array $payload)
    {
        $json = json_encode($payload);

        return $this->call('POST', "/api/webhooks/meta/{$this->whatsapp->webhook_key}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, 'app-secret'),
        ], $json);
    }

    private function inbound(string $from, string $text, string $id, ?string $name = null): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'metadata' => ['phone_number_id' => 'PHONE_ID'],
            'contacts' => $name ? [['profile' => ['name' => $name], 'wa_id' => $from]] : [],
            'messages' => [['from' => $from, 'id' => $id, 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function statusPayload(string $id, string $status, array $errors = []): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'statuses' => [['id' => $id, 'status' => $status, 'recipient_id' => '91', 'errors' => $errors]],
        ]]]]]];
    }
}
