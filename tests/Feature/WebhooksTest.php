<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhook;
use App\Models\Lead;
use App\Models\User;
use App\Models\Webhook;
use App\Services\LeadService;
use App\Webhooks\UrlGuard;
use App\Webhooks\WebhookPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // A fake DNS: tests never reach the real network.
        $this->app->instance(UrlGuard::class, new UrlGuard(fn (string $host) => match ($host) {
            'hooks.example.com' => ['93.184.216.34'],
            'sneaky.example.com' => ['10.0.0.5'],
            default => [],
        }));
        $this->admin = $this->registerOrganization();
        $this->actingAs($this->admin);
    }

    private function webhook(array $events, ?User $owner = null): Webhook
    {
        $webhook = new Webhook(['url' => 'https://hooks.example.com/crm', 'events' => $events, 'is_active' => true]);
        $webhook->organization_id = ($owner ?? $this->admin)->organization_id;
        $webhook->secret = 'top-secret';
        $webhook->save();

        return $webhook;
    }

    public function test_only_public_https_addresses_can_be_added(): void
    {
        $add = fn (string $url) => $this->post('/settings/webhooks', ['url' => $url, 'events' => ['lead.created']]);

        $add('http://hooks.example.com/crm')->assertSessionHasErrors('url');
        $add('https://sneaky.example.com/crm')->assertSessionHasErrors(['url' => 'That address points to a private or local network.']);
        $add('https://127.0.0.1/crm')->assertSessionHasErrors('url');
        $add('https://[::1]/crm')->assertSessionHasErrors('url');
        $add('https://hooks.example.com:8080/crm')->assertSessionHasErrors('url');
        $this->post('/settings/webhooks', ['url' => 'https://hooks.example.com/crm'])->assertSessionHasErrors('events');

        $add('https://hooks.example.com/crm')->assertSessionHasNoErrors();
        $this->assertSame(40, strlen(Webhook::sole()->secret), 'a secret is generated');
        $this->get('/settings/webhooks')->assertSee('https://hooks.example.com/crm');

        $this->actingAs($this->addAgent($this->admin->organization))->get('/settings/webhooks')->assertForbidden();
    }

    public function test_events_are_delivered_signed_to_the_apps_that_asked_for_them(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response(['ok' => true])]);
        $wantsNewLeads = $this->webhook(['lead.created']);
        $this->webhook(['lead.won']);
        $this->webhook(['lead.created'], $this->registerOrganization('Other'));

        app(LeadService::class)->create($this->admin->organization, ['name' => 'Kiran Rao', 'phone' => '+919811111111']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->header('X-Convera-Event')[0] === 'lead.created'
            && $request->header('X-Convera-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), 'top-secret')
            && $request['lead']['name'] === 'Kiran Rao'
            && $request['workspace']['id'] === $this->admin->organization_id);
        $this->assertSame(200, $wantsNewLeads->fresh()->last_status);
    }

    public function test_a_won_lead_sends_both_the_stage_change_and_won_events(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response()]);
        $lead = Lead::factory()->for($this->admin->organization)->create();
        $this->webhook(['lead.status_changed', 'lead.won', 'lead.lost']);
        $won = $this->admin->organization->leadStatuses()->where('name', 'Won')->value('id');

        $this->patch("/leads/{$lead->id}/status", ['status_id' => $won]);

        $events = collect(Http::recorded())->map(fn ($pair) => $pair[0]['event'])->all();
        $this->assertSame(['lead.status_changed', 'lead.won'], $events);
        Http::assertSent(fn (Request $request) => $request['event'] === 'lead.status_changed' && $request['data']['previous_status'] === 'New');
    }

    public function test_failures_are_recorded_retried_and_eventually_switch_the_webhook_off(): void
    {
        $webhook = $this->webhook(['lead.created']);
        $deliver = fn () => (new DeliverWebhook($webhook->id, WebhookPayload::ping($this->admin->organization)))->handle(app(UrlGuard::class));

        Http::fakeSequence()->push('down', 503)->push('gone', 410);
        try {
            $deliver();
            $this->fail('a server error should be retried');
        } catch (RuntimeException) {
        }
        $this->assertSame('The app answered HTTP 503.', $webhook->fresh()->last_error);
        $this->assertSame(1, $webhook->fresh()->failures);

        $webhook->forceFill(['failures' => Webhook::MAX_FAILURES - 1])->save();
        $deliver(); // a 4xx is final: no retry, no exception
        $this->assertFalse($webhook->fresh()->is_active, 'switched off after too many failures in a row');
    }

    public function test_an_address_that_turns_private_later_is_never_called(): void
    {
        Http::fake();
        $webhook = $this->webhook(['lead.created']);
        $webhook->forceFill(['url' => 'https://sneaky.example.com/crm'])->save(); // e.g. DNS changed after saving

        (new DeliverWebhook($webhook->id, WebhookPayload::ping($this->admin->organization)))->handle(app(UrlGuard::class));

        Http::assertNothingSent();
        $this->assertStringStartsWith('Blocked', $webhook->fresh()->last_error);
    }

    public function test_send_test_reports_what_the_app_answered(): void
    {
        $webhook = $this->webhook(['lead.created']);

        Http::fakeSequence()->push('ok', 200)->push('nope', 404);
        $this->post("/settings/webhooks/{$webhook->id}/test")->assertSessionHas('status', 'Test delivered: the app answered HTTP 200.');
        Http::assertSent(fn (Request $request) => $request['event'] === 'ping');

        $this->post("/settings/webhooks/{$webhook->id}/test")->assertSessionHasErrors(['test' => 'Test failed: The app answered HTTP 404.']);
    }
}
