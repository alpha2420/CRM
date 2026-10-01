<div class="grid-2">
    <section class="card">
        <form method="post" action="{{ route('settings.integrations.update', $type) }}" class="stack">
            @csrf @method('put')
            <label>Page access token <input type="password" name="page_access_token" autocomplete="off" placeholder="{{ $integration ? 'Saved — leave blank to keep' : '' }}" @required(! $integration)></label>
            <label>App secret <input type="password" name="app_secret" autocomplete="off" placeholder="{{ $integration ? 'Saved — leave blank to keep' : '' }}" @required(! $integration)></label>
            <div class="form-actions"><button type="submit" class="btn primary" @disabled($locked)>{{ $integration ? 'Save' : 'Connect' }}</button></div>
        </form>
    </section>
    <section class="card">
        <h3 class="section-label" style="margin-top:0">How to connect</h3>
        <ol class="steps">
            <li>In your Meta app, add the <strong>Webhooks</strong> product and choose the <strong>Page</strong> object.</li>
            <li>Create a long-lived Page access token with <code>leads_retrieval</code> and <code>pages_manage_metadata</code>, and copy it with the app secret into this form.</li>
            @if ($integration)
                <li>Set the webhook and subscribe to the <code>leadgen</code> field:
                    <div class="kv"><span>Callback URL</span><code class="key">{{ route('webhooks.meta', $integration->webhook_key) }}</code></div>
                    <div class="kv"><span>Verify token</span><code class="key">{{ $settings['verify_token'] }}</code></div></li>
                <li>Subscribe your Page to the app. Use Meta's Lead Ads Testing Tool to send a test lead.</li>
            @else
                <li>Save this form to get your webhook URL and verify token.</li>
            @endif
        </ol>
    </section>
</div>
