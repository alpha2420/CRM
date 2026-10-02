@extends('layouts.app')
@section('title', 'New broadcast')
@section('subtitle', 'Choose the message, choose who gets it, check the summary, send.')

@section('content')
    <a href="{{ route('broadcasts.index') }}" class="back"><x-icon name="arrow-left"/>Broadcasts</a>
    @if ($templates->isEmpty())
        <div class="alert warning">There are no approved WhatsApp templates yet. Create one in WhatsApp Manager, then click <b>Sync from WhatsApp</b> under <a href="{{ route('settings.integrations.edit', 'whatsapp') }}">Settings → Integrations → WhatsApp</a>.</div>
    @endif

    <form method="post" action="{{ route('broadcasts.store') }}" class="grid-main broadcast-form" data-broadcast-form>
        @csrf
        <div class="col-stack">
            <section class="card stack">
                <h2><span class="step-no">1</span>Message</h2>
                <label>Name <span class="hint">only your team sees it</span>
                    <input name="name" value="{{ old('name') }}" maxlength="100" placeholder="e.g. Diwali offer to open leads">
                </label>
                <label>Approved template
                    <select name="whatsapp_template_id" data-preview-on-change>
                        <option value="">Choose a template…</option>
                        @foreach ($templates as $option)
                            <option value="{{ $option->id }}" @selected($template?->id === $option->id)>{{ $option->label() }} · {{ ucfirst(strtolower((string) $option->category)) }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($template)
                    <p class="template-preview">{{ $template->body }}</p>
                    @for ($i = 2; $i <= $template->variables; $i++)
                        <label>Value for &#123;&#123;{{ $i }}&#125;&#125;
                            <input name="values[{{ $i }}]" value="{{ old("values.{$i}") }}" maxlength="200" placeholder="{{ $i === 2 ? 'Your company name, unless you type something' : 'The same for everyone' }}">
                        </label>
                    @endfor
                    @if ($template->variables >= 1)<p class="hint" style="margin:0">&#123;&#123;1&#125;&#125; is filled with each lead's first name.</p>@endif
                @endif
            </section>

            <section class="card stack">
                <h2><span class="step-no">2</span>Who gets it</h2>
                <div class="form-grid two">
                    <label>Leads
                        <select name="audience[stage]">
                            <option value="open" @selected(($chosen['stage'] ?? 'open') === 'open')>All open leads</option>
                            <option value="won" @selected(($chosen['stage'] ?? '') === 'won')>Customers (won)</option>
                            <option value="all" @selected(($chosen['stage'] ?? '') === 'all')>All leads</option>
                            @foreach ($stages as $stage)<option value="{{ $stage->id }}" @selected((string) ($chosen['stage'] ?? '') === (string) $stage->id)>Only “{{ $stage->name }}”</option>@endforeach
                        </select>
                    </label>
                    <label>Source
                        <select name="audience[source_id]">
                            <option value="">Any source</option>
                            @foreach ($sources as $source)<option value="{{ $source->id }}" @selected((string) ($chosen['source_id'] ?? '') === (string) $source->id)>{{ $source->name }}</option>@endforeach
                        </select>
                    </label>
                    <label>Campaign
                        <select name="audience[campaign]">
                            <option value="">Any campaign</option>
                            @foreach ($campaigns as $campaign)<option value="{{ $campaign }}" @selected(($chosen['campaign'] ?? '') === $campaign)>{{ $campaign }}</option>@endforeach
                        </select>
                    </label>
                    <label>Owner
                        <select name="audience[assigned_to]">
                            <option value="">Anyone's leads</option>
                            @foreach ($people as $person)<option value="{{ $person->id }}" @selected((string) ($chosen['assigned_to'] ?? '') === (string) $person->id)>{{ $person->name }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <div><button type="submit" name="preview" value="1" class="btn"><x-icon name="users"/>Count leads</button></div>
            </section>
        </div>

        <aside class="col-stack">
            <section class="card broadcast-summary">
                <h2><span class="step-no">3</span>Check and send</h2>
                <div class="speed-headline">
                    <strong>{{ number_format($size['count']) }}</strong>
                    <span>{{ Str::plural('lead', $size['count']) }} will get it<span class="muted small">{{ number_format($size['opted_out']) }} who said STOP are left out{{ $size['count'] >= $max ? ' · at most '.number_format($max).' at a time' : '' }}</span></span>
                </div>
                @if ($template)
                    <p class="cost">Meta charges about <b>{{ \App\Support\Money::full($cost) }}</b> for these ({{ strtolower((string) ($template->category ?: 'marketing')) }} messages; Meta bills you directly).</p>
                @endif
                <p class="hint">Messages go out one by one over the next few minutes. Only send to people who expect to hear from you: WhatsApp limits numbers whose messages get blocked or reported.</p>
                @error('confirm')<p class="field-error">{{ $message }}</p>@enderror
                <label class="check"><input type="checkbox" name="confirm" value="1"> Yes, send it now</label>
                <button type="submit" class="btn primary block" @disabled($templates->isEmpty() || $size['count'] === 0)><x-icon name="send"/>Send to {{ number_format($size['count']) }} {{ Str::plural('lead', $size['count']) }}</button>
            </section>
        </aside>
    </form>
@endsection
