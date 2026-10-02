{{-- One line of "My day": a follow-up, a meeting or a to-do. --}}
@php($when = \App\Support\FollowUp::describe($item->at))
<li class="agenda-item kind-{{ $item->kind }}">
    @if ($item->kind === \App\Tasks\AgendaItem::TASK)
        <form method="post" action="{{ route('tasks.update', $item->task) }}" class="tick-form">
            @csrf @method('patch')
            <input type="hidden" name="done" value="1">
            <button type="submit" class="tick" aria-label="Mark “{{ $item->title }}” done" title="Mark done"><x-icon name="check"/></button>
        </form>
    @else
        <span class="agenda-icon"><x-icon :name="$item->icon()"/></span>
    @endif

    <div class="agenda-body">
        @if ($item->lead)
            <a href="{{ route('leads.show', $item->lead) }}" class="agenda-title">{{ $item->title }}</a>
        @else
            <span class="agenda-title">{{ $item->title }}</span>
        @endif
        <span class="agenda-sub">
            @if ($item->kind === \App\Tasks\AgendaItem::FOLLOW_UP)
                {{ $item->lead->status?->name }}{{ $item->lead->company ? ' · '.$item->lead->company : '' }}
            @elseif ($item->kind === \App\Tasks\AgendaItem::MEETING)
                {{ $item->meeting->location ?: 'No place set' }}
            @elseif ($onLead ?? false)
                {{ $item->task->user_id === auth()->id() ? 'For you' : 'For '.$item->task->assignee?->name }}
            @else
                {{ $item->lead ? $item->lead->name : 'To-do' }}
            @endif
        </span>
    </div>

    <span class="meta">
        @if ($item->at)<span class="due {{ $item->kind === \App\Tasks\AgendaItem::MEETING ? 'today' : $when['tone'] }}">{{ $when['text'] }}</span>@endif
        @if ($item->kind === \App\Tasks\AgendaItem::FOLLOW_UP)
            @include('partials.contact-tools', ['lead' => $item->lead])
            <a href="{{ route('leads.show', $item->lead) }}#log" class="btn small">Log call</a>
        @elseif ($item->kind === \App\Tasks\AgendaItem::TASK)
            <form method="post" action="{{ route('tasks.destroy', $item->task) }}" data-confirm="Remove this to-do?">
                @csrf @method('delete')
                <button type="submit" class="icon-btn" aria-label="Remove “{{ $item->title }}”"><x-icon name="x"/></button>
            </form>
        @endif
    </span>
</li>
