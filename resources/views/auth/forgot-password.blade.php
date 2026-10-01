@extends('layouts.guest')
@section('title', 'Reset your password')
@section('lead-in', "Enter your email and we'll send you a link to choose a new password.")

@section('content')
    <form method="post" action="{{ route('password.email') }}" class="stack">
        @csrf
        <label>Work email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <button type="submit" class="btn primary large block">Send reset link</button>
        <p class="center"><a href="{{ route('login') }}" class="small">Back to log in</a></p>
    </form>
@endsection
