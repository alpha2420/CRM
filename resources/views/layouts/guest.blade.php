<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
<body>
<div class="auth">
    <div class="auth-form">
        <a href="{{ url('/') }}" class="logo"><img src="{{ asset('icons/icon-192.png') }}" alt="">{{ config('app.name') }}</a>
        <div class="auth-box">
            <h1>@yield('title')</h1>
            @hasSection('lead-in')<p class="lead-in">@yield('lead-in')</p>@endif
            @include('partials.flash')
            @yield('content')
        </div>
        <div class="auth-foot">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </div>
    <aside class="auth-panel">
        <h2>Every lead, followed up on time.</h2>
        <p>Capture leads from WhatsApp, your website and ads. Assign them instantly. Never miss a follow-up again.</p>
        <ul>
            <li><x-icon name="check-circle"/>WhatsApp conversations inside your CRM</li>
            <li><x-icon name="check-circle"/>Automatic lead assignment and reminders</li>
            <li><x-icon name="check-circle"/>Reports that show who converts, and how fast</li>
        </ul>
        @if (file_exists(public_path('images/app-dashboard.webp')))
            <img src="{{ asset('images/app-dashboard.webp') }}" alt="" loading="lazy">
        @endif
    </aside>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
