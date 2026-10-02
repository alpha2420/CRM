@extends('layouts.settings')

@section('settings')
    @php($organization = auth()->user()->organization)
    @php($plan = $organization->plan())
    @php($seats = $organization->seatsUsed())
    <div class="settings-head">
        <div><h2>Team</h2><p>Agents work only the leads assigned to them. Admins see everything and manage settings.</p></div>
        <a href="{{ route('users.create') }}" class="btn">Add with a password</a>
    </div>

    <section class="card">
        <div class="card-head" style="margin-bottom:10px"><div><h2>Invite by email</h2><p class="muted small">They get a link to set their own name and password. Links expire in {{ \App\Models\Invitation::VALID_DAYS }} days.</p></div></div>
        <form method="post" action="{{ route('invitations.store') }}" class="inline-form">
            @csrf
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" placeholder="colleague@company.com" style="flex:1; min-width:220px" aria-label="Email">
            <select name="role" aria-label="Role">
                @foreach (\App\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', 'agent') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn primary"><x-icon name="user-plus"/>Send invite</button>
        </form>
        @if (session('invite_link'))
            <div class="alert info mt" style="margin-bottom:0">
                <div class="grow">You can also share this link directly (e.g. on WhatsApp):
                    <code class="key mt" id="invite-link">{{ session('invite_link') }}</code>
                    <button type="button" class="btn small mt" data-copy="#invite-link">Copy link</button>
                </div>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="row-between"><strong>{{ $seats }} of {{ $plan->maxUsers }} seats used</strong><a href="{{ route('settings.billing') }}" class="small">{{ $plan->name }} plan</a></div>
        <div class="meter mt"><span style="width: {{ min(100, $seats / max(1, $plan->maxUsers) * 100) }}%"></span></div>
    </section>

    <section class="card flush">
        <div class="scroll-x">
            <table>
                <thead><tr><th>Member</th><th>Role</th><th>Status</th><th class="num">Leads</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td><span class="cell-main"><x-avatar :name="$user->name"/><span><strong>{{ $user->name }}@if ($user->is(auth()->user())) <span class="faint small">(you)</span>@endif</strong><span class="sub">{{ $user->email }}</span></span></span></td>
                        <td><span @class(['pill', 'info' => $user->isAdmin()])>{{ $user->role->label() }}</span></td>
                        <td>{!! $user->is_active ? '<span class="pill ok">Active</span>' : '<span class="pill">Deactivated</span>' !!}</td>
                        <td class="num">{{ $user->assigned_leads_count }}</td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('users.edit', $user) }}" class="btn small">Edit</a>
                                @unless ($user->is(auth()->user()))
                                    <form method="post" action="{{ route('users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}? Their leads will become unassigned.">
                                        @csrf @method('delete')
                                        <button type="submit" class="icon-btn" aria-label="Delete {{ $user->name }}"><x-icon name="trash"/></button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @if ($invitations->isNotEmpty())
        <section class="card flush">
            <div class="card-head"><h2>Pending invitations</h2><span class="muted small">{{ $invitations->count() }} waiting</span></div>
            <table>
                <tbody>
                @foreach ($invitations as $invitation)
                    <tr>
                        <td><span class="cell-main"><x-avatar :name="$invitation->email"/><span><strong>{{ $invitation->email }}</strong><span class="sub">Invited by {{ $invitation->inviter?->name ?? '—' }} · expires {{ $invitation->expires_at->diffForHumans() }}</span></span></span></td>
                        <td><span class="pill">{{ $invitation->role->label() }}</span></td>
                        <td>
                            <div class="row-actions">
                                <form method="post" action="{{ route('invitations.store') }}">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ $invitation->email }}">
                                    <input type="hidden" name="role" value="{{ $invitation->role->value }}">
                                    <button type="submit" class="btn small">Resend</button>
                                </form>
                                <form method="post" action="{{ route('invitations.destroy', $invitation) }}" data-confirm="Revoke the invitation for {{ $invitation->email }}?">
                                    @csrf @method('delete')
                                    <button type="submit" class="icon-btn" aria-label="Revoke"><x-icon name="x"/></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
