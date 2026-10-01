@extends('layouts.app')
@section('title', $organization->name)
@section('subtitle', 'Joined '.$organization->created_at->format('d M Y'))
@section('actions')
    <a href="{{ route('platform.index') }}" class="btn ghost"><x-icon name="arrow-left"/>All workspaces</a>
@endsection

@section('content')
    <div class="grid-2">
        <section class="card">
            <div class="card-head"><h2>Account</h2></div>
            <dl class="details">
                <dt>Status</dt><dd>@include('platform._status')</dd>
                <dt>Plan</dt><dd>{{ $organization->plan()->name }}</dd>
                <dt>Trial ends</dt><dd>{{ $organization->trial_ends_at?->format('d M Y') ?? '—' }}</dd>
                <dt>Subscription</dt><dd>{{ $organization->subscription_status ?? '—' }} {{ $organization->razorpay_subscription_id ? '('.$organization->razorpay_subscription_id.')' : '' }}</dd>
                <dt>Paid until</dt><dd>{{ $organization->current_period_end?->format('d M Y') ?? '—' }}</dd>
                <dt>Users</dt><dd>{{ $users->count() }}</dd>
                <dt>Leads</dt><dd>{{ number_format($leadCount) }}</dd>
                <dt>Joined</dt><dd>{{ $organization->created_at->format('d M Y') }}</dd>
            </dl>
        </section>

        <section class="card stack">
            <div class="card-head" style="margin:0"><h2>Actions</h2></div>
            <form method="post" action="{{ route('platform.extend-trial', $organization) }}" class="inline-form">
                @csrf
                <input type="number" name="days" value="14" min="1" max="90" required>
                <button type="submit" class="btn">Extend trial (days)</button>
            </form>
            <form method="post" action="{{ route('platform.grant-plan', $organization) }}" class="inline-form">
                @csrf
                <select name="plan" required>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->key }}">{{ $plan->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="paid_until" value="{{ now()->addMonth()->toDateString() }}" required>
                <button type="submit" class="btn">Record offline payment</button>
            </form>
            <form method="post" action="{{ route('platform.suspend', $organization) }}" data-confirm="Are you sure?">
                @csrf
                <button type="submit" class="btn {{ $organization->isSuspended() ? '' : 'danger' }}">{{ $organization->isSuspended() ? 'Reactivate workspace' : 'Suspend workspace' }}</button>
            </form>
        </section>
    </div>

    <section class="card flush">
        <div class="card-head"><h2>Users</h2></div>
        <table>
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td><span class="person"><x-avatar :name="$user->name" size="sm"/>{{ $user->name }}</span></td>
                    <td class="muted">{{ $user->email }}</td>
                    <td>{{ $user->role->label() }}</td>
                    <td>{!! $user->is_active ? '<span class="pill ok">Active</span>' : '<span class="pill">Inactive</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
