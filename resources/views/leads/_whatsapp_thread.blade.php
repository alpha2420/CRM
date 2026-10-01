<div class="thread" data-count="{{ $messages->count() }}">
    @php($lastDay = null)
    @forelse ($messages as $message)
        @php($day = $message->created_at->local()->isToday() ? 'Today' : ($message->created_at->local()->isYesterday() ? 'Yesterday' : $message->created_at->local()->format('d M Y')))
        @if ($day !== $lastDay)
            <div class="center" style="margin:6px 0"><span class="pill">{{ $day }}</span></div>
            @php($lastDay = $day)
        @endif
        <div @class(['bubble', 'in' => $message->isInbound(), 'out' => ! $message->isInbound()])>
            @if ($message->type === 'template')<div class="bubble-tag">Template · {{ $message->template_name }}</div>@endif
            <div class="bubble-body">{{ $message->body }}</div>
            <div class="bubble-meta">
                @unless ($message->isInbound()){{ $message->user?->name ?? 'Automation' }} · @endunless{{ $message->created_at->local()->format('H:i') }}
                @unless ($message->isInbound())
                    <span @class(['state', $message->status])>{{ match ($message->status) { 'queued' => '· sending…', 'sent' => '✓', 'delivered' => '✓✓', 'read' => '✓✓', 'failed' => '· failed', default => '' } }}</span>
                @endunless
            </div>
            @if ($message->error)<div class="bubble-error">{{ $message->error }}</div>@endif
        </div>
    @empty
        <div class="center muted" style="margin:auto">No messages yet. Say hello 👋</div>
    @endforelse
</div>
