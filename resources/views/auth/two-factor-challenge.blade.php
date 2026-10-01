@extends('layouts.guest')
@section('title', 'Two-factor login')
@section('lead-in', 'Enter the 6-digit code from your authenticator app.')

@section('content')
    <form method="post" action="{{ route('two-factor.challenge') }}" class="stack">
        @csrf
        <label>Code
            <input name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="20" placeholder="123 456" style="font-size:20px; letter-spacing:.2em">
            <span class="hint">Lost your phone? Enter one of your recovery codes instead.</span>
        </label>
        <button type="submit" class="btn primary large block">Verify and log in</button>
        <p class="center"><a href="{{ route('login') }}" class="small">Back to log in</a></p>
    </form>
@endsection
