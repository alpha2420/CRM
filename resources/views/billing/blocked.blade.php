@extends('layouts.simple')
@section('title', $reason === 'suspended' ? 'Workspace suspended' : 'Plan ended')

@section('content')
    <h1>{{ $reason === 'suspended' ? 'Workspace suspended' : 'Your plan has ended' }}</h1>
    @if ($reason === 'suspended')
        <p class="muted">This workspace has been suspended. Please contact support to restore access.
            @if (config('crm.support_email'))<a href="mailto:{{ config('crm.support_email') }}">{{ config('crm.support_email') }}</a>@endif
        </p>
    @else
        <p class="muted">The plan for <strong>{{ auth()->user()->organization->name }}</strong> has ended. Ask your admin to renew it — your leads are safe in the meantime.</p>
    @endif
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn">Log out</button>
    </form>
@endsection
