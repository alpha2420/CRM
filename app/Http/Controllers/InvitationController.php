<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Invitation;
use App\Services\AuditLogger;
use App\Services\TeamInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /** Admin: send an invitation. */
    public function store(Request $request, TeamInvitations $invitations): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $url = $invitations->invite($request->user()->organization, $data['email'], Role::from($data['role']), $request->user());

        return back()->with('status', "Invitation sent to {$data['email']}.")->with('invite_link', $url);
    }

    /** Admin: withdraw a pending invitation. */
    public function destroy(Invitation $invitation, AuditLogger $audit): RedirectResponse
    {
        $invitation->delete();
        $audit->log('user.invite_revoked', "Revoked the invitation for {$invitation->email}");

        return back()->with('status', 'Invitation revoked.');
    }

    /** Invitee: the page behind the emailed link. */
    public function show(string $token): View
    {
        $invitation = Invitation::findPendingByToken($token);

        return $invitation
            ? view('auth.accept-invitation', ['invitation' => $invitation, 'token' => $token])
            : view('auth.invitation-invalid');
    }

    public function accept(Request $request, string $token, TeamInvitations $invitations): RedirectResponse|View
    {
        $invitation = Invitation::findPendingByToken($token);

        if ($invitation === null) {
            return view('auth.invitation-invalid');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $invitations->accept($invitation, $data['name'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', "Welcome to {$invitation->organization->name}!");
    }
}
