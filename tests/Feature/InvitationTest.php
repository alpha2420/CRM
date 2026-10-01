<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_invites_a_colleague_who_joins_with_their_own_password(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization('Sunrise');

        $this->actingAs($admin)->post('/users/invitations', ['email' => 'Priya@Example.com', 'role' => 'agent'])
            ->assertSessionHas('invite_link');
        $link = session('invite_link');
        Notification::assertSentTo(new AnonymousNotifiable, InvitationNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'priya@example.com');
        $this->get('/users')->assertSee('Pending invitations')->assertSee('priya@example.com');
        $this->post('/logout');

        $this->get($link)->assertOk()->assertSee('Join Sunrise');
        $this->post($link, ['name' => 'Priya Nair', 'password' => 'a-strong-pass', 'password_confirmation' => 'a-strong-pass'])->assertRedirect('/dashboard');

        $priya = User::where('email', 'priya@example.com')->sole();
        $this->assertAuthenticatedAs($priya);
        $this->assertSame($admin->organization_id, $priya->organization_id);
        $this->assertSame(Role::Agent, $priya->role);
        $this->assertTrue($priya->hasVerifiedEmail());

        // The link works once.
        $this->post('/logout');
        $this->get($link)->assertOk()->assertSee('This invitation has expired');
    }

    public function test_invitations_respect_seats_existing_accounts_and_expiry(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $other = $this->registerOrganization('Other');

        $this->actingAs($admin)->post('/users/invitations', ['email' => $other->email, 'role' => 'agent'])->assertSessionHasErrors('email');

        $admin->organization->forceFill(['plan' => 'starter', 'subscription_status' => 'active'])->save(); // 3 seats
        $this->actingAs($admin->fresh())->post('/users/invitations', ['email' => 'a@example.com', 'role' => 'agent'])->assertSessionHasNoErrors();
        $this->post('/users/invitations', ['email' => 'b@example.com', 'role' => 'agent'])->assertSessionHasNoErrors();
        $this->post('/users/invitations', ['email' => 'c@example.com', 'role' => 'agent'])->assertSessionHasErrors('email');

        $link = $this->post('/users/invitations', ['email' => 'a@example.com', 'role' => 'admin'])->getSession()->get('invite_link');
        $this->assertSame(2, Invitation::count(), 'Resending replaces the old invitation.');

        $this->travel(8)->days();
        $this->post('/logout');
        $this->get($link)->assertSee('This invitation has expired');
    }

    public function test_admins_revoke_invitations_and_agents_cannot_invite(): void
    {
        Notification::fake();
        $admin = $this->registerOrganization();
        $agent = $this->addAgent($admin->organization);
        $this->actingAs($admin)->post('/users/invitations', ['email' => 'x@example.com', 'role' => 'agent']);
        $invitation = Invitation::sole();

        $this->delete("/users/invitations/{$invitation->id}")->assertSessionHas('status', 'Invitation revoked.');
        $this->assertSame(0, Invitation::count());

        $this->actingAs($agent)->post('/users/invitations', ['email' => 'y@example.com', 'role' => 'admin'])->assertForbidden();
    }
}
