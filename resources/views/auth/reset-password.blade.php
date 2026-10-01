@extends('layouts.guest')
@section('title', 'Choose a new password')

@section('content')
    <form method="post" action="{{ route('password.store') }}" class="stack">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Work email <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"></label>
        <label>New password <input type="password" name="password" required minlength="8" autocomplete="new-password" autofocus></label>
        <label>Confirm new password <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        <button type="submit" class="btn primary large block">Save password</button>
    </form>
@endsection
