<section class="card">
    @if ($integration)
        <ol class="steps">
            <li>In Google Ads, open your lead form asset → <strong>Lead delivery</strong> → <strong>Webhook integration</strong>.</li>
            <li>Paste these two values:
                <div class="kv"><span>Webhook URL</span><code class="key">{{ route('webhooks.google', $integration->webhook_key) }}</code></div>
                <div class="kv"><span>Key</span><code class="key">{{ $settings['google_key'] }}</code></div></li>
            <li>Click <strong>Send test data</strong>. A test lead appears in your CRM within seconds.</li>
        </ol>
    @else
        <p>Turn this on to get a webhook URL and key to paste into your Google Ads lead form.</p>
        <form method="post" action="{{ route('settings.integrations.update', $type) }}">
            @csrf @method('put')
            <button type="submit" class="btn primary" @disabled($locked)>Turn on</button>
        </form>
    @endif
</section>
