@extends('layouts.guest')
@section('title', 'Check your inbox')

@section('content')
    <p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to open your workspace.</p>
    @if ($localLink)
        <div class="alert warning">
            <div>Running locally: emails go to <code>storage/logs/laravel.log</code> instead of being sent.
                <div class="mt"><a href="{{ $localLink }}" class="btn primary small local-verify">Verify now</a></div></div>
        </div>
    @endif
    <form method="post" action="{{ route('verification.send') }}" class="stack">
        @csrf
        <button type="submit" class="btn large block">Send the link again</button>
    </form>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <p class="muted small center mt">Wrong email? <button type="submit" class="link">Log out</button></p>
    </form>
@endsection
