<?php

namespace App\Http\Controllers;

use App\Autopilot\Autopilot;
use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = $request->user()->organization->users()
            ->withCount('assignedLeads')
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users' => $users,
            'invitations' => Invitation::query()->pending()->with('inviter')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User(['role' => Role::Agent, 'is_active' => true])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $organization = $request->user()->organization;

        if ($request->boolean('is_active') && ! $organization->hasFreeSeat()) {
            return back()->withInput()->withErrors(['email' => $this->seatLimitMessage($organization)]);
        }

        // The admin vouches for the address, so no verification email is needed.
        $organization->users()->create($request->validated())->markEmailAsVerified();

        return redirect()->route('users.index')->with('status', 'User added.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user, Autopilot $autopilot): RedirectResponse
    {
        $data = array_filter($request->validated(), fn ($value, $key) => $key !== 'password' || filled($value), ARRAY_FILTER_USE_BOTH);

        // Never let admins lock themselves out of their own workspace.
        if ($request->user()->is($user) && ($data['role'] !== Role::Admin->value || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot remove your own admin access or deactivate yourself.']);
        }

        if ($data['is_active'] && ! $user->is_active && ! $user->organization->hasFreeSeat()) {
            return back()->withErrors(['is_active' => $this->seatLimitMessage($user->organization)]);
        }

        $user->update($data);
        $passedOn = $user->wasChanged('is_active') && ! $user->is_active ? $autopilot->memberLeft($user) : 0;

        return redirect()->route('users.index')->with('status', 'User updated.'.$this->handOverNote($passedOn));
    }

    public function destroy(User $user, Autopilot $autopilot): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $passedOn = $autopilot->memberLeft($user);
        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.'.($passedOn ? $this->handOverNote($passedOn) : ' Their remaining leads are now unassigned.'));
    }

    private function handOverNote(int $count): string
    {
        return $count ? " {$count} open ".Str::plural('lead', $count).' shared with the team.' : '';
    }

    private function seatLimitMessage(Organization $organization): string
    {
        $plan = $organization->plan();

        return "Your {$plan->name} plan allows {$plan->maxUsers} active users. Upgrade under Billing or deactivate someone first.";
    }
}
