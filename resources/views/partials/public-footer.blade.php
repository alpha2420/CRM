<footer class="l-footer">
    <div class="l-container l-footer-inner">
        <div>
            <a href="/" class="logo"><img src="{{ asset('icons/icon-192.png') }}" alt="">{{ config('app.name') }}</a>
            <p class="muted small">The simple CRM for WhatsApp-first sales teams.</p>
        </div>
        <nav>
            <a href="{{ url('/') }}#features">Features</a>
            <a href="{{ url('/') }}#pricing">Pricing</a>
            <a href="{{ url('/') }}#faq">FAQ</a>
            <a href="{{ route('login') }}">Log in</a>
            <a href="{{ route('register') }}">Start free trial</a>
            <a href="{{ route('legal', 'privacy') }}">Privacy</a>
            <a href="{{ route('legal', 'terms') }}">Terms</a>
            @if (config('crm.support_email'))<a href="mailto:{{ config('crm.support_email') }}">{{ config('crm.support_email') }}</a>@endif
        </nav>
    </div>
    <div class="l-container faint small">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
</footer>
