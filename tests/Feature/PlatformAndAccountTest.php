<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PlatformAndAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_up_goes_straight_in_while_verification_is_off(): void
    {
        Notification::fake();
        config(['crm.require_email_verification' => false]);

        $this->post('/register', [
            'organization_name' => 'Acme', 'name' => 'Owner', 'email' => 'owner@acme.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        Notification::assertNotSentTo(User::where('email', 'owner@acme.test')->sole(), VerifyEmail::class);
        $this->assertTrue(User::where('email', 'owner@acme.test')->sole()->hasVerifiedEmail());
    }

    public function test_new_owners_must_verify_their_email_when_switched_on(): void
    {
        Notification::fake();
        config(['crm.require_email_verification' => true]);

        $this->post('/register', [
            'organization_name' => 'Acme', 'name' => 'Owner', 'email' => 'owner@acme.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ]);
        $user = User::where('email', 'owner@acme.test')->sole();

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/dashboard')->assertRedirect('/email/verify');

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/dashboard');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/dashboard')->assertOk();
    }

    public function test_the_verify_now_shortcut_appears_only_for_local_development(): void
    {
        $user = $this->registerOrganization();
        $user->forceFill(['email_verified_at' => null])->save();
        config(['mail.default' => 'log', 'crm.require_email_verification' => true]);

        $this->app['env'] = 'production';
        $this->actingAs($user)->get('/email/verify')->assertOk()->assertDontSee('Verify now');

        $this->app['env'] = 'local';
        $this->get('/email/verify')->assertOk()->assertSee('Verify now');
    }

    public function test_users_reset_a_forgotten_password(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();

        $this->post('/forgot-password', ['email' => $admin->email])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use ($admin) {
            $this->post('/reset-password', [
                'token' => $notification->token, 'email' => $admin->email,
                'password' => 'a-new-password', 'password_confirmation' => 'a-new-password',
            ])->assertRedirect('/login');

            return true;
        });

        $this->post('/login', ['email' => $admin->email, 'password' => 'a-new-password'])->assertRedirect('/dashboard');
    }

    public function test_only_platform_admins_reach_the_platform_panel(): void
    {
        $owner = $this->registerOrganization('Operator');
        $customer = $this->registerOrganization('Customer');
        config(['crm.platform_admins' => [strtolower($owner->email)]]);

        $this->actingAs($customer)->get('/platform')->assertForbidden();
        $this->actingAs($owner)->get('/platform')->assertOk()->assertSee('Customer');
        $this->get("/platform/workspaces/{$customer->organization_id}")->assertOk();
    }

    public function test_platform_admin_suspends_extends_and_grants_plans(): void
    {
        $owner = $this->registerOrganization('Operator');
        $customer = $this->registerOrganization('Customer');
        config(['crm.platform_admins' => [strtolower($owner->email)]]);
        $workspace = $customer->organization;

        $this->actingAs($owner)->post("/platform/workspaces/{$workspace->id}/suspend")->assertRedirect();
        $this->actingAs($customer->fresh())->get('/dashboard')->assertForbidden();

        $this->actingAs($owner)->post("/platform/workspaces/{$workspace->id}/suspend");
        $this->actingAs($owner)->post("/platform/workspaces/{$workspace->id}/extend-trial", ['days' => 10]);
        $this->assertSame(24, $workspace->fresh()->trialDaysLeft());

        $this->post("/platform/workspaces/{$workspace->id}/grant-plan", ['plan' => 'pro', 'paid_until' => now()->addMonth()->toDateString()]);
        $workspace->refresh();
        $this->assertSame('pro', $workspace->plan);
        $this->assertTrue($workspace->hasPaidAccess());
        $this->actingAs($customer->fresh())->get('/dashboard')->assertOk();
    }
}
