<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invite people by email; they choose their own name and password.
 */
final class TeamInvitations
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Send (or re-send) an invitation. Returns the link, so the admin can
     * also share it directly.
     */
    public function invite(Organization $organization, string $email, Role $role, User $inviter): string
    {
        $email = Str::lower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This person already has an account.']);
        }

        $pendingForOthers = Invitation::query()->pending()->where('email', '!=', $email)->count();
        if ($organization->seatsUsed() + $pendingForOthers >= $organization->plan()->maxUsers) {
            throw ValidationException::withMessages(['email' => "Your {$organization->plan()->name} plan allows {$organization->plan()->maxUsers} users, including pending invitations. Upgrade or revoke an invitation first."]);
        }

        $token = Str::random(40);

        $invitation = DB::transaction(function () use ($organization, $email, $role, $inviter, $token) {
            Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

            $invitation = new Invitation(['email' => $email, 'role' => $role]);
            $invitation->organization_id = $organization->id;
            $invitation->token_hash = hash('sha256', $token);
            $invitation->invited_by = $inviter->id;
            $invitation->expires_at = now()->addDays(Invitation::VALID_DAYS);
            $invitation->save();

            return $invitation;
        });

        $url = route('invitations.show', $token);
        Notification::route('mail', $email)->notify(new InvitationNotification($invitation->load('organization', 'inviter'), $url));
        $this->audit->log('user.invited', "Invited {$email} as {$role->label()}", $invitation, $inviter);

        return $url;
    }

    public function accept(Invitation $invitation, string $name, string $password): User
    {
        $organization = $invitation->organization;

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['name' => 'An account with this email already exists. Log in instead.']);
        }

        if (! $organization->hasFreeSeat()) {
            throw ValidationException::withMessages(['name' => 'This workspace has no free seats right now. Ask your admin to upgrade.']);
        }

        return DB::transaction(function () use ($invitation, $organization, $name, $password) {
            $user = $organization->users()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
            ]);
            $user->markEmailAsVerified(); // they proved the address by opening the link

            $invitation->forceFill(['accepted_at' => now()])->save();
            $this->audit->log('user.joined', "{$name} accepted the invitation", $user, $user);

            return $user;
        });
    }
}
