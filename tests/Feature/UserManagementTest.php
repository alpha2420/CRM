<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_an_agent_who_can_then_log_in(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)->post('/users', [
            'name' => 'New Agent',
            'email' => 'agent@example.com',
            'role' => 'agent',
            'is_active' => '1',
            'password' => 'agent-password',
            'password_confirmation' => 'agent-password',
        ])->assertRedirect('/users');

        $agent = User::where('email', 'agent@example.com')->sole();
        $this->assertSame($admin->organization_id, $agent->organization_id);
        $this->assertSame(Role::Agent, $agent->role);

        $this->post('/logout');
        $this->post('/login', ['email' => 'agent@example.com', 'password' => 'agent-password'])->assertRedirect('/dashboard');
    }

    public function test_agents_cannot_manage_users_or_settings(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);

        $this->actingAs($agent);
        $this->get('/users')->assertForbidden();
        $this->get('/settings/statuses')->assertForbidden();
        $this->get('/settings/workspace')->assertForbidden();
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'agent', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->put("/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->delete("/users/{$admin->id}")->assertForbidden();

        $this->assertTrue($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_updating_without_a_password_keeps_the_old_one(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $hash = $agent->password;

        $this->actingAs($admin)
            ->put("/users/{$agent->id}", ['name' => 'Renamed', 'email' => $agent->email, 'role' => 'agent', 'is_active' => '1', 'password' => ''])
            ->assertRedirect('/users');

        $this->assertSame('Renamed', $agent->fresh()->name);
        $this->assertSame($hash, $agent->fresh()->password);
    }

    public function test_deleting_a_user_unassigns_their_leads(): void
    {
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $lead = Lead::factory()->for($admin->organization)->create(['assigned_to' => $agent->id]);

        $this->actingAs($admin)->delete("/users/{$agent->id}")->assertRedirect('/users');

        $this->assertNull($agent->fresh());
        $this->assertNull($lead->fresh()->assigned_to);
    }

    public function test_users_update_their_own_profile_and_password(): void
    {
        $admin = $this->registerOrganization();

        $this->actingAs($admin)
            ->put('/profile', ['name' => 'Me', 'email' => $admin->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertSessionHasErrors('current_password');

        $this->put('/profile', [
            'name' => 'Me', 'email' => $admin->email,
            'current_password' => 'password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/profile');

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'brand-new-pass'])->assertRedirect('/dashboard');
    }
}
