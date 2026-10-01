<header class="l-nav">
    <div class="l-container l-nav-inner">
        <a href="/" class="logo"><img src="{{ asset('icons/icon-192.png') }}" alt="">{{ config('app.name') }}</a>
        <nav class="l-links">
            <a href="{{ url('/') }}#features">Features</a>
            <a href="{{ url('/') }}#whatsapp">WhatsApp</a>
            <a href="{{ url('/') }}#pricing">Pricing</a>
            <a href="{{ url('/') }}#faq">FAQ</a>
        </nav>
        <div class="l-nav-cta">
            <a href="{{ route('login') }}" class="btn ghost">Log in</a>
            <a href="{{ route('register') }}" class="btn primary">Start free trial</a>
        </div>
    </div>
</header>
