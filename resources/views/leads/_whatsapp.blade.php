@php($context ??= 'lead')
<section class="card flush chat">
    <div id="thread" data-url="{{ route('leads.whatsapp.thread', $lead) }}" class="chat">
        @include('leads._whatsapp_thread', ['messages' => $messages])
    </div>

    @can('update', $lead)
        @if ($lead->whatsappWindowOpen())
            <form method="post" action="{{ route('leads.whatsapp.send', $lead) }}" class="composer">
                @csrf
                <input type="hidden" name="from" value="{{ $context }}">
                <textarea name="body" id="wa-body" rows="1" maxlength="4096" placeholder="Type a message" required>{{ old('body', request('draft')) }}</textarea>
                <button type="submit" class="btn primary"><x-icon name="send"/>Send</button>
            </form>
            <div class="composer-note">Free replies are open until {{ $lead->last_inbound_at->local()->addDay()->format('d M, H:i') }}.</div>
        @else
            <div class="template-picker">
                <div class="muted small">WhatsApp only allows approved templates until {{ Str::before($lead->name, ' ') }} replies.</div>
                @php($approved = $templates->filter->isApproved())
                @if ($approved->isEmpty())
                    <p class="muted" style="margin:0">No approved templates yet. @can('admin')<a href="{{ route('settings.integrations.edit', 'whatsapp') }}">Sync templates</a>@endcan</p>
                @else
                    <form method="get" class="inline-form">
                        @if ($context === 'inbox')
                            <input type="hidden" name="lead" value="{{ $lead->id }}">
                        @else
                            <input type="hidden" name="tab" value="whatsapp">
                        @endif
                        <select name="template" data-autosubmit aria-label="Template">
                            <option value="">Choose a template…</option>
                            @foreach ($approved as $template)
                                <option value="{{ $template->id }}" @selected($selectedTemplate?->id === $template->id)>{{ $template->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($selectedTemplate)
                        <form method="post" action="{{ route('leads.whatsapp.send', $lead) }}" class="stack">
                            @csrf
                            <input type="hidden" name="from" value="{{ $context }}">
                            <input type="hidden" name="template_id" value="{{ $selectedTemplate->id }}">
                            <p class="template-preview">{{ $selectedTemplate->body }}</p>
                            @for ($i = 1; $i <= $selectedTemplate->variables; $i++)
                                <label>Value for &#123;&#123;{{ $i }}&#125;&#125;
                                    <input name="parameters[]" value="{{ $templateDefaults[$i] ?? '' }}" required maxlength="500">
                                </label>
                            @endfor
                            <div><button type="submit" class="btn primary">Send template</button></div>
                        </form>
                    @endif
                @endif
            </div>
        @endif
    @endcan
</section>

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    // Keep the conversation fresh every 15 seconds while it is open.
    (function () {
        const box = document.getElementById('thread');
        const scroll = () => { const t = box.querySelector('.thread'); if (t) t.scrollTop = t.scrollHeight; };
        scroll();
        setInterval(async () => {
            if (document.hidden) return;
            try {
                const res = await fetch(box.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (res.ok) { box.innerHTML = await res.text(); scroll(); }
            } catch (e) { /* offline: try again next tick */ }
        }, 15000);
    })();
</script>
@endpush
