<div class="grid-2">
    <section class="card">
        <form method="post" action="{{ route('settings.integrations.update', $type) }}" class="stack">
            @csrf @method('put')
            <label>Phone number ID <input name="phone_number_id" value="{{ old('phone_number_id', $settings['phone_number_id'] ?? '') }}" required></label>
            <label>WhatsApp Business Account ID <input name="waba_id" value="{{ old('waba_id', $settings['waba_id'] ?? '') }}" required></label>
            <label>Permanent access token <input type="password" name="access_token" autocomplete="off" placeholder="{{ $integration ? 'Saved — leave blank to keep' : '' }}" @required(! $integration)></label>
            <label>App secret <input type="password" name="app_secret" autocomplete="off" placeholder="{{ $integration ? 'Saved — leave blank to keep' : '' }}" @required(! $integration)></label>
            <label>Default country code <input name="default_country_code" value="{{ old('default_country_code', $settings['default_country_code'] ?? '91') }}" required maxlength="5"></label>
            <div class="form-actions"><button type="submit" class="btn primary" @disabled($locked)>{{ $integration ? 'Save' : 'Connect WhatsApp' }}</button></div>
        </form>
    </section>
    <section class="card">
        <h3 class="section-label" style="margin-top:0">How to connect</h3>
        <ol class="steps">
            <li>In <a href="https://business.facebook.com" target="_blank" rel="noopener">Meta Business Suite</a>, create an app with the WhatsApp product and add your business number.</li>
            <li>Create a System User with a permanent token (permissions: <code>whatsapp_business_messaging</code>, <code>whatsapp_business_management</code>).</li>
            <li>Copy the Phone number ID, Business Account ID, token and the app secret into this form.</li>
            @if ($integration)
                <li>In the app's WhatsApp → Configuration, set the webhook:
                    <div class="kv"><span>Callback URL</span><code class="key">{{ route('webhooks.meta', $integration->webhook_key) }}</code></div>
                    <div class="kv"><span>Verify token</span><code class="key">{{ $settings['verify_token'] }}</code></div>
                    and subscribe to <code>messages</code>.</li>
            @else
                <li>Save this form to get your webhook URL and verify token.</li>
            @endif
        </ol>
    </section>
</div>

@if ($integration)
    <section class="card">
        <div class="row-between">
            <h3 class="section-label" style="margin-top:0">Message templates</h3>
            <form method="post" action="{{ route('settings.integrations.templates') }}">
                @csrf
                <button type="submit" class="btn small">Sync from WhatsApp</button>
            </form>
        </div>
        <p class="muted">Create templates in WhatsApp Manager; Meta approves them. Use approved templates to start conversations, and in automations to greet new leads instantly. <code>&#123;&#123;1&#125;&#125;</code> is filled with the lead's first name, <code>&#123;&#123;2&#125;&#125;</code> with your company name.</p>
        <table>
            <thead><tr><th>Template</th><th>Category</th><th>Status</th><th>Text</th></tr></thead>
            <tbody>
            @forelse ($templates as $template)
                <tr>
                    <td>{{ $template->label() }}</td>
                    <td>{{ ucfirst(strtolower((string) $template->category)) }}</td>
                    <td><span @class(['pill', 'ok' => $template->isApproved()])>{{ ucfirst(strtolower($template->status)) }}</span></td>
                    <td class="muted">{{ Str::limit($template->body, 90) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No templates yet. Click "Sync from WhatsApp".</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endif
