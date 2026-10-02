@extends('layouts.app')
@section('title', 'Leads')
@section('subtitle')
    @if ($view === 'board')
        {{ number_format($openCount) }} open {{ Str::plural('lead', $openCount) }} · <strong>{{ \App\Support\Money::short($openValue) }}</strong> in the pipeline
    @else
        {{ number_format($leads->total()) }} {{ Str::plural('lead', $leads->total()) }}{{ $stage !== \App\Enums\LeadStage::All ? ' · '.$stage->label() : '' }}
    @endif
@endsection
@section('actions')
    @php($keep = Arr::except(request()->query(), ['view', 'page', 'stage']))
    <div class="segmented" role="group" aria-label="View">
        <a href="{{ route('leads.index', $keep) }}" @class(['active' => $view === 'list'])><x-icon name="list"/>List</a>
        <a href="{{ route('leads.index', $keep + ['view' => 'board']) }}" @class(['active' => $view === 'board'])><x-icon name="board"/>Board</a>
    </div>
    @can('admin')<a href="{{ route('leads.import') }}" class="btn"><x-icon name="upload"/>Import / Export</a>@endcan
@endsection
@if ($view === 'board')
    @section('wide', true)
@endif

@section('content')
    @php($stage ??= \App\Enums\LeadStage::All)
    @php($stageParam = fn ($s) => $s === \App\Enums\LeadStage::All ? null : $s->value)

    @if ($view === 'list')
        <nav class="tabs" aria-label="Lead views">
            @foreach (\App\Enums\LeadStage::cases() as $tab)
                <a href="{{ route('leads.index', array_filter(['stage' => $stageParam($tab)])) }}" @class(['active' => $tab === $stage])>
                    {{ $tab->label() }}
                    @if ($stageCounts[$tab->value] > 0 && $tab !== \App\Enums\LeadStage::All)<span class="count">{{ number_format($stageCounts[$tab->value]) }}</span>@endif
                </a>
            @endforeach
        </nav>
    @endif

    <form method="get" class="filters">
        @if ($view === 'board')<input type="hidden" name="view" value="board">@endif
        @if ($stage !== \App\Enums\LeadStage::All)<input type="hidden" name="stage" value="{{ $stage->value }}">@endif
        <div class="search-field">
            <x-icon name="search"/>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name, phone, email or company" aria-label="Search leads">
        </div>
        @if ($view === 'list')
            <select name="status_id" aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="source_id" aria-label="Source">
            <option value="">All sources</option>
            @foreach ($sources as $source)
                <option value="{{ $source->id }}" @selected(($filters['source_id'] ?? '') == $source->id)>{{ $source->name }}</option>
            @endforeach
        </select>
        @can('admin')
            <select name="assigned_to" aria-label="Owner">
                <option value="">Any owner</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(($filters['assigned_to'] ?? '') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        @endcan
        @php($moreActive = filled($filters['priority'] ?? null) || filled($filters['from'] ?? null) || filled($filters['to'] ?? null))
        <details class="more-filters" @if ($moreActive) open @endif>
            <summary>More filters</summary>
            <div class="more-filters-body">
                <select name="priority" aria-label="Priority">
                    <option value="">Any priority</option>
                    @foreach (\App\Enums\Priority::cases() as $priority)
                        <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
                <label class="inline">Added from <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
                <label class="inline">to <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            </div>
        </details>
        <button type="submit" class="btn">Apply</button>
        @if ($filters)
            <a href="{{ route('leads.index', array_filter(['stage' => $stageParam($stage), 'view' => $view === 'board' ? 'board' : null])) }}" class="btn ghost">Clear</a>
        @endif
        @if ($view === 'board')<span class="board-hint hide-sm" style="margin-left:auto">Drag a card to change its stage</span>@endif
    </form>

    @if ($view === 'board')
        <div class="board" data-move-url="{{ route('leads.move', ['lead' => '__ID__']) }}">
            @foreach ($columns as $column)
                @php($closed = $column->status->type !== \App\Enums\StatusType::Open)
                <section @class(['board-col', 'closed' => $closed]) data-status="{{ $column->status->id }}" aria-label="{{ $column->status->name }}">
                    <header class="board-head">
                        <span class="dot" style="background: {{ $column->status->color }}"></span>
                        <strong>{{ $column->status->name }}</strong>
                        <span class="n" data-count>{{ number_format($column->count) }}</span>
                        <span class="sum" data-sum="{{ $column->value }}">{{ $column->value ? \App\Support\Money::short($column->value) : '' }}</span>
                    </header>
                    <div class="board-cards">
                        @foreach ($column->leads as $lead)
                            @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                            <a href="{{ route('leads.show', $lead) }}" class="deal" draggable="true" data-id="{{ $lead->id }}" data-value="{{ (float) $lead->value }}">
                                <span>
                                    <span class="deal-name">{{ $lead->name }}@if ($lead->priority === \App\Enums\Priority::High)<span class="flag">High</span>@endif</span>
                                    <span class="deal-sub">{{ $lead->company ?: $lead->phone }}</span>
                                </span>
                                @if ($lead->source)<span class="deal-meta"><x-icon name="tag"/>{{ $lead->source->name }}</span>@endif
                                <span class="deal-foot">
                                    @if ($lead->value)
                                        <span class="deal-value">{{ \App\Support\Money::full($lead->value) }}</span>
                                    @else
                                        <span class="deal-value none">No value</span>
                                    @endif
                                    <span class="end">
                                        @if (! $closed && $due['tone'] !== 'none')<span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span>@endif
                                        @if ($lead->assignee)<x-avatar :name="$lead->assignee->name" size="sm" title="{{ $lead->assignee->name }}"/>@endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                    @if ($column->count > $column->leads->count())
                        <a class="board-more" href="{{ route('leads.index', $filters + ['status_id' => $column->status->id]) }}">+{{ number_format($column->count - $column->leads->count()) }} more in the list</a>
                    @endif
                    @unless ($closed)
                        <a class="board-add" href="{{ route('leads.create', ['status_id' => $column->status->id]) }}"><x-icon name="plus" class="icon sm"/>Add lead</a>
                    @endunless
                </section>
            @endforeach
        </div>
    @else
        <form method="post" action="{{ route('leads.bulk') }}" class="card flush" id="bulk-form">
            @csrf
            <div class="bulk-bar" id="bulk-bar" hidden>
                <span class="selected-count" id="selected-count">0 selected</span>
                <select name="operation" required aria-label="Bulk action">
                    <option value="">Choose an action…</option>
                    <optgroup label="Set status">
                        @foreach ($statuses as $status)
                            <option value="status:{{ $status->id }}">{{ $status->name }}</option>
                        @endforeach
                    </optgroup>
                    @can('admin')
                        <optgroup label="Assign to">
                            @foreach ($users as $user)
                                <option value="assign:{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Other">
                            <option value="delete">Delete</option>
                        </optgroup>
                    @endcan
                </select>
                <button type="submit" class="btn small primary">Apply</button>
            </div>

            @if ($leads->isEmpty())
                @if (! $filters && $stage === \App\Enums\LeadStage::All)
                    <x-empty icon="leads" title="No leads yet" text="Add your first lead, import a spreadsheet, or share your lead form and let them come to you.">
                        <a href="{{ route('leads.create') }}" class="btn primary"><x-icon name="plus"/>Add lead</a>
                        @can('admin')
                            <a href="{{ route('leads.import') }}" class="btn"><x-icon name="upload"/>Import CSV</a>
                            <a href="{{ route('settings.integrations.edit', 'web_form') }}" class="btn"><x-icon name="globe"/>Lead form</a>
                        @endcan
                    </x-empty>
                @elseif ($stage === \App\Enums\LeadStage::Due && ! $filters)
                    <x-empty icon="check-circle" title="You're all caught up" text="No follow-ups are due today. Nice work."/>
                @else
                    <x-empty icon="search" title="No leads here" text="Nothing matches this view right now."/>
                @endif
            @else
                <div class="scroll-x">
                    <table>
                        <thead>
                        <tr>
                            <th class="check"><input type="checkbox" id="select-all" aria-label="Select all on this page"></th>
                            <th>Lead</th><th>Status</th><th>Next follow-up</th><th class="num hide-sm">Value</th><th>Owner</th><th class="hide-sm">Source</th><th class="hide-sm">Added</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($leads as $lead)
                            @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                            <tr>
                                <td class="check"><input type="checkbox" name="ids[]" value="{{ $lead->id }}" class="row-check" aria-label="Select {{ $lead->name }}"></td>
                                <td>
                                    <a href="{{ route('leads.show', $lead) }}" class="cell-main">
                                        <x-avatar :name="$lead->name"/>
                                        <span>
                                            <strong>{{ $lead->name }}@if ($lead->priority === \App\Enums\Priority::High)<span class="flag"><x-icon name="flag" class="icon sm"/>High</span>@endif</strong>
                                            <span class="sub">{{ $lead->company ? $lead->company.' · ' : '' }}{{ $lead->phone }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td>@include('partials.status', ['status' => $lead->status])</td>
                                <td><span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span></td>
                                <td class="num hide-sm">{{ $lead->value ? \App\Support\Money::full($lead->value) : '' }}</td>
                                <td>
                                    @if ($lead->assignee)
                                        <span class="person"><x-avatar :name="$lead->assignee->name" size="sm"/>{{ Str::before($lead->assignee->name, ' ') }}</span>
                                    @else
                                        <span class="faint">Unassigned</span>
                                    @endif
                                </td>
                                <td class="hide-sm muted">{{ $lead->source?->name ?? '—' }}</td>
                                <td class="hide-sm muted nowrap" title="{{ $lead->created_at->local()->format('d M Y, H:i') }}">{{ $lead->created_at->diffForHumans(short: true) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </form>

        {{ $leads->links() }}
    @endif
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        // List: select rows for bulk actions.
        const checks = () => [...document.querySelectorAll('.row-check')];
        const update = () => {
            const n = checks().filter((c) => c.checked).length;
            document.getElementById('selected-count').textContent = `${n} selected`;
            document.getElementById('bulk-bar').hidden = n === 0;
        };
        document.getElementById('select-all')?.addEventListener('change', (e) => { checks().forEach((c) => c.checked = e.target.checked); update(); });
        checks().forEach((c) => c.addEventListener('change', update));

        document.getElementById('bulk-form')?.addEventListener('submit', (e) => {
            if (e.target.operation.value === 'delete' && !confirm('Delete the selected leads?')) e.preventDefault();
        });

        // Board: drag a card to another column to change its stage.
        const board = document.querySelector('.board');
        if (!board) return;
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const money = (v) => v >= 1e7 ? `₹${+(v / 1e7).toFixed(1)}Cr` : v >= 1e5 ? `₹${+(v / 1e5).toFixed(1)}L` : v >= 1e3 ? `₹${+(v / 1e3).toFixed(1)}K` : v > 0 ? `₹${Math.round(v)}` : '';
        const adjust = (col, delta, value) => {
            const count = col.querySelector('[data-count]');
            const sum = col.querySelector('[data-sum]');
            count.textContent = Math.max(0, parseInt(count.textContent.replace(/\D/g, ''), 10) + delta).toLocaleString('en-IN');
            sum.dataset.sum = Math.max(0, parseFloat(sum.dataset.sum) + delta * value);
            sum.textContent = money(parseFloat(sum.dataset.sum));
        };
        const toast = (text) => {
            const box = document.querySelector('.toasts') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'toasts', role: 'status' }));
            const el = Object.assign(document.createElement('div'), { className: 'toast', textContent: text });
            box.appendChild(el);
            setTimeout(() => { el.classList.add('leaving'); setTimeout(() => el.remove(), 300); }, 2500);
        };
        let dragged = null;

        board.addEventListener('dragstart', (e) => {
            dragged = e.target.closest('.deal');
            if (!dragged) return;
            dragged.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragged.dataset.id);
        });
        board.addEventListener('dragend', () => {
            dragged?.classList.remove('dragging');
            board.querySelectorAll('.drop').forEach((c) => c.classList.remove('drop'));
        });
        board.querySelectorAll('.board-col').forEach((col) => {
            col.addEventListener('dragover', (e) => { if (dragged) { e.preventDefault(); col.classList.add('drop'); } });
            col.addEventListener('dragleave', (e) => { if (!col.contains(e.relatedTarget)) col.classList.remove('drop'); });
            col.addEventListener('drop', async (e) => {
                e.preventDefault();
                col.classList.remove('drop');
                const card = dragged;
                const from = card?.closest('.board-col');
                if (!card || from === col) return;
                const value = parseFloat(card.dataset.value) || 0;
                col.querySelector('.board-cards').prepend(card);
                card.classList.add('saving');
                adjust(from, -1, value); adjust(col, 1, value);
                try {
                    const res = await fetch(board.dataset.moveUrl.replace('__ID__', card.dataset.id), {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status_id: col.dataset.status }),
                    });
                    if (!res.ok) throw new Error();
                    toast(`${card.querySelector('.deal-name').firstChild.textContent.trim()} moved to ${(await res.json()).status.name}`);
                } catch {
                    from.querySelector('.board-cards').prepend(card);
                    adjust(col, -1, value); adjust(from, 1, value);
                    toast('Could not move that lead. Please try again.');
                } finally {
                    card.classList.remove('saving');
                }
            });
        });
    })();
</script>
@endpush
