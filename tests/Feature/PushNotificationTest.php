<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Lead;
use App\Models\PushSubscription;
use App\Push\PushSender;
use App\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
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

    public function test_the_real_sender_reports_an_unreachable_device_instead_of_crashing(): void
    {
        $vapid = VAPID::createVapidKeys();
        config(['services.webpush.public_key' => $vapid['publicKey'], 'services.webpush.private_key' => $vapid['privateKey'], 'services.webpush.subject' => 'mailto:ops@example.com']);
        $device = openssl_pkey_get_details(openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]))['ec'];
        $base64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $subscription = new PushSubscription([
            'endpoint' => 'http://127.0.0.1:9/push', // nothing listens here
            'public_key' => $base64("\x04".str_pad($device['x'], 32, "\0", STR_PAD_LEFT).str_pad($device['y'], 32, "\0", STR_PAD_LEFT)),
            'auth_token' => $base64(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
        ]);

        $this->assertSame(PushSender::FAILED, (new WebPushSender)->send($subscription, ['title' => 'Hi', 'body' => 'Test', 'url' => '/']));
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
