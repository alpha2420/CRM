@extends('layouts.guest')
@section('title', 'Start your free trial')
@section('lead-in', '14 days, every feature, no credit card.')

@section('content')
    <form method="post" action="{{ route('register') }}" class="stack">
        @csrf
        <input type="hidden" name="timezone" id="timezone">
        <label>Company name <input name="organization_name" value="{{ old('organization_name') }}" required maxlength="100" autofocus placeholder="Acme Realty"></label>
        <label>Your name <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"></label>
        <label>Work email <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="username" placeholder="you@company.com"></label>
        <div class="form-grid">
            <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirm <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        </div>
        <button type="submit" class="btn primary large block">Create my workspace</button>
        <p class="muted small center" style="margin:0">By creating an account you agree to the <a href="{{ route('legal', 'terms') }}" target="_blank">Terms</a> and <a href="{{ route('legal', 'privacy') }}" target="_blank">Privacy Policy</a>.</p>
        <p class="muted small center">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    </form>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    try { document.getElementById('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
</script>
@endpush
