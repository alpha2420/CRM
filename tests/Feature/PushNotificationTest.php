<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Lead;
use App\Models\PushSubscription;
use App\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new FakePushSender;
        $this->app->instance(PushSender::class, $this->sender);
    }

    public function test_a_device_subscribes_and_unsubscribes(): void
    {
        $agent = $this->addAgent($this->registerOrganization()->organization);
        $payload = ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-key'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($agent)->postJson('/push-subscriptions', $payload)->assertOk();
        $this->postJson('/push-subscriptions', $payload)->assertOk();
        $this->assertSame(1, $agent->pushSubscriptions()->count(), 'Re-subscribing the same device does not duplicate it.');

        $this->postJson('/push-subscriptions', ['endpoint' => 'http://insecure.example.com', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])->assertUnprocessable();

        $this->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, $agent->pushSubscriptions()->count());
    }

    public function test_new_lead_assignments_are_pushed_to_the_agents_devices(): void
    {
        Queue::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $agent->pushSubscriptions()->create(['endpoint' => 'https://push.example.com/a', 'endpoint_hash' => hash('sha256', 'a'), 'public_key' => 'k', 'auth_token' => 't']);

        $this->actingAs($admin)->post('/leads', ['name' => 'Kiran Rao', 'phone' => '9000000001', 'priority' => 'medium']);

        $lead = Lead::sole();
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $agent->id
            && $job->message['title'] === 'New lead: Kiran Rao'
            && $job->message['url'] === url("/leads/{$lead->id}"));
    }

    public function test_the_job_delivers_and_forgets_devices_that_unsubscribed(): void
    {
        $user = $this->registerOrganization();
        $live = $user->pushSubscriptions()->create(['endpoint' => 'https://push.example.com/live', 'endpoint_hash' => hash('sha256', 'live'), 'public_key' => 'k', 'auth_token' => 't']);
        $gone = $user->pushSubscriptions()->create(['endpoint' => 'https://push.example.com/gone', 'endpoint_hash' => hash('sha256', 'gone'), 'public_key' => 'k', 'auth_token' => 't']);
        $this->sender->responses = [$gone->id => PushSender::EXPIRED];

        (new SendPushNotification($user->id, ['title' => 'Hi', 'body' => 'There', 'url' => '/']))->handle($this->sender);

        $this->assertSame([$live->id, $gone->id], $this->sender->sentTo);
        $this->assertNotNull($live->fresh());
        $this->assertNull($gone->fresh());
    }

    public function test_nothing_is_sent_or_shown_when_push_is_not_configured(): void
    {
        $this->sender->configured = false;
        $user = $this->registerOrganization();
        $user->pushSubscriptions()->create(['endpoint' => 'https://push.example.com/x', 'endpoint_hash' => hash('sha256', 'x'), 'public_key' => 'k', 'auth_token' => 't']);

        (new SendPushNotification($user->id, ['title' => 'Hi', 'body' => '', 'url' => '/']))->handle($this->sender);

        $this->assertSame([], $this->sender->sentTo);
        $this->actingAs($user)->get('/notifications')->assertDontSee('Notifications on this device');
    }
}

class FakePushSender implements PushSender
{
    public bool $configured = true;

    /** @var array<int, string> */
    public array $responses = [];

    /** @var list<int> */
    public array $sentTo = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(PushSubscription $subscription, array $message): string
    {
        $this->sentTo[] = $subscription->id;

        return $this->responses[$subscription->id] ?? self::DELIVERED;
    }
}
