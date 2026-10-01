<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_up_creates_a_ready_to_use_workspace(): void
    {
        $this->post('/register', [
            'organization_name' => 'Acme Sales',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'owner@acme.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertSame('Acme Sales', $user->organization->name);
        $this->assertSame(count(config('crm.default_statuses')), $user->organization->leadStatuses()->count());
        $this->assertSame(count(config('crm.default_sources')), $user->organization->sources()->count());
    }

    public function test_sign_up_rejects_an_email_already_in_use(): void
    {
        $admin = $this->registerOrganization();

        $this->post('/register', [
            'organization_name' => 'Copycat',
            'name' => 'Someone',
            'email' => $admin->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_users_can_log_in_and_out(): void
    {
        $admin = $this->registerOrganization();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_inactive_users_are_refused(): void
    {
        $admin = $this->registerOrganization();
        $inactive = $this->addAgent($admin->organization, ['is_active' => false]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_deactivated_mid_session_is_signed_out(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent);
        $agent->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/leads')->assertRedirect('/login');
    }

    public function test_login_is_rate_limited(): void
    {
        $admin = $this->registerOrganization();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertTooManyRequests();
    }
}
