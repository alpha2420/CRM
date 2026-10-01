<div class="grid-2">
    <section class="card">
        <form method="post" action="{{ route('settings.integrations.update', $type) }}" class="stack">
            @csrf @method('put')
            <label>Form title <input name="title" value="{{ old('title', $settings['title'] ?? 'Get in touch') }}" required maxlength="100"></label>
            <label>Button text <input name="button" value="{{ old('button', $settings['button'] ?? 'Send') }}" required maxlength="40"></label>
            <label>Thank-you message <textarea name="thank_you" rows="2" required maxlength="300">{{ old('thank_you', $settings['thank_you'] ?? 'Thanks! We will call you shortly.') }}</textarea></label>
            <div class="muted">Name and phone are always asked. Also ask for:</div>
            <label class="check"><input type="checkbox" name="ask_email" value="1" @checked($settings['ask_email'] ?? true)> Email</label>
            <label class="check"><input type="checkbox" name="ask_city" value="1" @checked($settings['ask_city'] ?? false)> City</label>
            <label class="check"><input type="checkbox" name="ask_message" value="1" @checked($settings['ask_message'] ?? true)> Message</label>
            <div class="form-actions"><button type="submit" class="btn primary">{{ $integration ? 'Save' : 'Create form' }}</button></div>
        </form>
    </section>
    @if ($integration)
        <section class="card">
            <h3 class="section-label" style="margin-top:0">Share it</h3>
            <p class="muted">Link for WhatsApp, Instagram bio or email:</p>
            <code class="key">{{ route('web-form.show', $integration->webhook_key) }}</code>
            <p><a href="{{ route('web-form.show', $integration->webhook_key) }}" target="_blank" rel="noopener">Open the form &nearr;</a></p>
            <h3 class="section-label">Embed it</h3>
            <p class="muted">Paste into any web page:</p>
            <code class="key">&lt;iframe src="{{ route('web-form.show', $integration->webhook_key) }}" style="width:100%;max-width:480px;height:560px;border:0"&gt;&lt;/iframe&gt;</code>
        </section>
    @endif
</div>
