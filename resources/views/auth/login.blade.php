@extends('layouts.guest')
@section('title', 'Welcome back')
@section('lead-in', 'Log in to your workspace.')

@section('content')
    <form method="post" action="{{ route('login') }}" class="stack">
        @csrf
        <label>Work email <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com"></label>
        <label>
            <span class="row-between">Password <a href="{{ route('password.request') }}" class="small">Forgot password?</a></span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>
        <button type="submit" class="btn primary large block">Log in</button>
    </form>
    <div class="divider">New here?</div>
    <a href="{{ route('register') }}" class="btn large block">Start your free 14-day trial</a>
@endsection
