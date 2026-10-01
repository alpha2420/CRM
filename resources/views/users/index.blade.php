@extends('layouts.settings')

@section('settings')
    @php($organization = auth()->user()->organization)
    @php($plan = $organization->plan())
    @php($seats = $organization->seatsUsed())
    <div class="settings-head">
        <div><h2>Team</h2><p>Agents work only the leads assigned to them. Admins see everything and manage settings.</p></div>
        <a href="{{ route('users.create') }}" class="btn primary"><x-icon name="user-plus"/>Add member</a>
    </div>

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
@endsection
