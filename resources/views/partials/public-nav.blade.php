<header class="l-nav">
    <div class="l-container l-nav-inner">
        <x-logo href="/"/>
        <nav class="l-links" aria-label="Main">
            <a href="{{ url('/') }}#product">Product</a>
            <a href="{{ url('/') }}#whatsapp">WhatsApp</a>
            <a href="{{ url('/') }}#how">How it works</a>
            <a href="{{ url('/') }}#pricing">Pricing</a>
            <a href="{{ url('/') }}#faq">FAQ</a>
        </nav>
        <div class="l-nav-cta">
            <a href="{{ route('login') }}" class="l-btn light small">Log in</a>
            <a href="{{ route('register') }}" class="l-btn dark small">Start free trial</a>
        </div>
    </div>
</header>
