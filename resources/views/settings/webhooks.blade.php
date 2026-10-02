@extends('layouts.settings')
@section('guide', 'webhooks')

@section('settings')
    <div class="settings-head">
        <div><h2>Webhooks</h2><p>Send what happens in the CRM to other apps: Zapier, Make, Google Sheets (through Apps Script) or your own software. We POST a JSON message to their address each time an event you pick happens.</p></div>
    </div>

    @foreach ($webhooks as $webhook)
        <section @class(['card', 'webhook', 'paused' => ! $webhook->is_active])>
            <div class="row-between" style="align-items:flex-start; flex-wrap:wrap">
                <div class="grow">
                    <strong class="webhook-url">{{ $webhook->url }}</strong>
                    <div class="chips" style="margin-top:8px">
                        @foreach ($webhook->events as $event)<span class="pill">{{ \App\Enums\WebhookEvent::tryFrom($event)?->label() ?? $event }}</span>@endforeach
                    </div>
                    <p class="small muted" style="margin:8px 0 0">
                        @if (! $webhook->is_active)<span class="pill warn">Paused</span>@if ($webhook->failures >= \App\Models\Webhook::MAX_FAILURES) after {{ $webhook->failures }} failed deliveries in a row @endif ·@endif
                        @if ($webhook->last_delivered_at === null) No deliveries yet.
                        @elseif ($webhook->last_error) <span class="tone-hot">Last delivery failed {{ $webhook->last_delivered_at->diffForHumans() }}: {{ $webhook->last_error }}</span>
                        @else <span class="tone-ok">Last delivery OK (HTTP {{ $webhook->last_status }}) {{ $webhook->last_delivered_at->diffForHumans() }}</span>@endif
                    </p>
                </div>
                <div class="row-actions">
                    <form method="post" action="{{ route('settings.webhooks.test', $webhook) }}">@csrf<button type="submit" class="btn small" @disabled(! $webhook->is_active)>Send test</button></form>
                    <form method="post" action="{{ route('settings.webhooks.toggle', $webhook) }}">@csrf<button type="submit" class="btn small">{{ $webhook->is_active ? 'Pause' : 'Turn on' }}</button></form>
                    <form method="post" action="{{ route('settings.webhooks.destroy', $webhook) }}" data-confirm="Remove this webhook? The other app stops getting events.">@csrf @method('delete')<button type="submit" class="icon-btn" aria-label="Remove webhook"><x-icon name="trash"/></button></form>
                </div>
            </div>
            <details class="mt">
                <summary class="small">Signing secret</summary>
                <code class="key mt">{{ $webhook->secret }}</code>
                <p class="hint" style="margin:8px 0 0">Each request has the header <code>X-CRM-Signature: sha256=…</code>, the HMAC-SHA256 of the raw body with this secret. Check it to be sure a message really came from {{ config('app.name') }}.</p>
            </details>
        </section>
    @endforeach

    @if ($webhooks->count() < $max)
        <form method="post" action="{{ route('settings.webhooks.store') }}" class="card stack">
            @csrf
            <h2>{{ $webhooks->isEmpty() ? 'Add your first webhook' : 'Add a webhook' }}</h2>
            <label>Address <input type="url" name="url" value="{{ old('url') }}" required maxlength="500" placeholder="https://hooks.zapier.com/hooks/catch/…"><span class="hint">Must be a public https:// address.</span></label>
            <fieldset>
                <legend>Send these events</legend>
                <div class="check-grid">
                    @foreach (\App\Enums\WebhookEvent::cases() as $event)
                        <label class="check"><input type="checkbox" name="events[]" value="{{ $event->value }}" @checked(in_array($event->value, old('events', ['lead.created', 'lead.won']), true))>{{ $event->label() }}</label>
                    @endforeach
                </div>
            </fieldset>
            <div><button type="submit" class="btn primary"><x-icon name="plus"/>Add webhook</button></div>
        </form>
    @endif
@endsection
