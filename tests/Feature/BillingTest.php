<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'secret',
            'services.razorpay.webhook_secret' => 'whsec',
            'plans.plans.growth.razorpay_plan_id' => 'plan_growth123',
        ]);
    }

    public function test_new_workspaces_start_on_a_trial_with_every_feature(): void
    {
        $organization = $this->registerOrganization()->organization;

        $this->assertTrue($organization->onTrial());
        $this->assertSame(14, $organization->trialDaysLeft());
        $this->assertTrue($organization->isActive());
    }

    public function test_an_ended_trial_sends_admins_to_billing_and_blocks_agents(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $admin->organization->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->actingAs($admin)->get('/leads')->assertRedirect('/settings/billing');
        $this->get('/settings/billing')->assertOk()->assertSee('Choose Growth');
        $this->actingAs($agent)->get('/leads')->assertForbidden()->assertSee('Ask your admin');
    }

    public function test_choosing_a_plan_redirects_to_razorpay(): void
    {
        Http::fake(['api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_123', 'short_url' => 'https://rzp.io/i/abc', 'status' => 'created'])]);
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->post('/settings/billing/subscribe/growth')->assertRedirect('https://rzp.io/i/abc');

        Http::assertSent(fn ($request) => $request['plan_id'] === 'plan_growth123'
            && $request['notes']['organization_id'] === (string) $admin->organization_id
            && $request['notes']['plan'] === 'growth');
        $this->assertSame('sub_123', $admin->organization->fresh()->razorpay_subscription_id);
        $this->assertSame('trial', $admin->organization->fresh()->plan);
    }

    public function test_activation_webhook_switches_the_plan(): void
    {
        $organization = $this->registerOrganization()->organization;
        $organization->forceFill(['razorpay_subscription_id' => 'sub_123'])->save();

        $this->razorpayWebhook('evt_1', [
            'event' => 'subscription.activated',
            'payload' => ['subscription' => ['entity' => [
                'id' => 'sub_123', 'status' => 'active', 'current_end' => now()->addMonth()->timestamp,
                'notes' => ['organization_id' => (string) $organization->id, 'plan' => 'growth'],
            ]]],
        ])->assertOk();

        $organization->refresh();
        $this->assertSame('growth', $organization->plan);
        $this->assertTrue($organization->hasPaidAccess());
        $this->assertTrue($organization->isActive());
    }

    public function test_webhooks_with_a_bad_signature_or_replayed_ids_are_ignored(): void
    {
        $organization = $this->registerOrganization()->organization;
        $body = ['event' => 'subscription.activated', 'payload' => ['subscription' => ['entity' => [
            'id' => 'sub_9', 'status' => 'active', 'notes' => ['organization_id' => (string) $organization->id, 'plan' => 'pro'],
        ]]]];

        $this->postJson('/api/webhooks/razorpay', $body, ['X-Razorpay-Signature' => 'forged'])->assertStatus(400);
        $this->assertSame('trial', $organization->fresh()->plan);

        $this->razorpayWebhook('evt_2', $body)->assertOk();
        $organization->forceFill(['plan' => 'starter'])->save();
        $this->razorpayWebhook('evt_2', $body)->assertOk();
        $this->assertSame('starter', $organization->fresh()->plan, 'A replayed event must not be applied twice.');
    }

    public function test_cancelled_subscriptions_keep_access_until_the_period_ends(): void
    {
        $organization = Organization::factory()->create(['plan' => 'growth', 'trial_ends_at' => null]);
        $organization->forceFill(['subscription_status' => 'cancelled', 'current_period_end' => now()->addDays(5)])->save();
        $this->assertTrue($organization->isActive());

        $organization->forceFill(['current_period_end' => now()->subDay()])->save();
        $this->assertFalse($organization->fresh()->isActive());
    }

    public function test_plan_features_and_user_limits_are_enforced(): void
    {
        $admin = $this->registerOrganization();
        $organization = $admin->organization;
        $organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save();

        $this->assertFalse($organization->fresh()->canUse(Feature::WhatsApp));

        $this->addAgent($organization);
        $this->addAgent($organization);
        $this->actingAs($admin)->post('/users', [
            'name' => 'One Too Many', 'email' => 'extra@example.com', 'role' => 'agent', 'is_active' => '1',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    private function razorpayWebhook(string $eventId, array $body)
    {
        $json = json_encode($body);

        return $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $json, 'whsec'),
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
        ], $json);
    }
}
