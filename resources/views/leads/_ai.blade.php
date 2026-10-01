@php($insight = $lead->ai_insight)
<section class="card ai-card">
    <div class="card-head" style="margin-bottom:0">
        <h2 class="ai-title"><x-icon name="sparkles"/>AI assistant</h2>
        @if ($aiAvailable)
            <form method="post" action="{{ route('leads.ai', $lead) }}">
                @csrf
                <button type="submit" class="btn small" @disabled($aiRemaining === 0) onclick="this.disabled=true;this.textContent='Thinking…';this.form.submit()">{{ $insight ? 'Refresh' : 'Analyse lead' }}</button>
            </form>
        @endif
    </div>

    @if ($insight)
        <div class="ai-temperature">
            <span class="temp {{ $insight['temperature'] }}">{{ ucfirst($insight['temperature']) }}</span>
            <span class="muted">{{ $insight['reason'] }}</span>
        </div>
        <p style="margin:0">{{ $insight['summary'] }}</p>
        <div class="section-label">Next step</div>
        <p style="margin:0">{{ $insight['next_step'] }}</p>
        <div class="section-label">Suggested message</div>
        <p class="ai-message" style="margin:0">{{ $insight['suggested_message'] }}</p>
        <div class="row-between mt">
            @if ($whatsappEnabled && $lead->whatsappWindowOpen())
                <a class="btn small" href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp', 'draft' => $insight['suggested_message']]) }}"><x-icon name="whatsapp"/>Use in WhatsApp</a>
            @else
                <button type="button" class="btn small" onclick="navigator.clipboard.writeText(this.closest('.ai-card').querySelector('.ai-message').textContent).then(() => this.textContent = 'Copied ✓')">Copy message</button>
            @endif
            <span class="faint small">Updated {{ $lead->ai_insight_at->diffForHumans() }}</span>
        </div>
    @else
        <p class="muted" style="margin:12px 0 0">Get a summary of this lead, how warm it is, the best next step and a ready-to-send message.</p>
    @endif
    <p class="faint small" style="margin:12px 0 0">{{ $aiAvailable ? $aiRemaining.' analyses left this month.' : 'New analyses are unavailable right now.' }}</p>
</section>
