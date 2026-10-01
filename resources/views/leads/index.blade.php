@extends('layouts.app')
@section('title', 'Leads')
@section('subtitle', number_format($leads->total()).' '.Str::plural('lead', $leads->total()).($stage !== \App\Enums\LeadStage::All ? ' · '.$stage->label() : ''))
@section('actions')
    @can('admin')<a href="{{ route('leads.import') }}" class="btn"><x-icon name="upload"/>Import / Export</a>@endcan
    <a href="{{ route('leads.create') }}" class="btn primary"><x-icon name="plus"/>Add lead</a>
@endsection

@section('content')
    @php($stageParam = fn ($s) => $s === \App\Enums\LeadStage::All ? null : $s->value)
    <nav class="tabs">
        @foreach (\App\Enums\LeadStage::cases() as $tab)
            <a href="{{ route('leads.index', array_filter(['stage' => $stageParam($tab)])) }}" @class(['active' => $tab === $stage])>
                {{ $tab->label() }}
                @if ($stageCounts[$tab->value] > 0 && $tab !== \App\Enums\LeadStage::All)<span class="count">{{ number_format($stageCounts[$tab->value]) }}</span>@endif
            </a>
        @endforeach
    </nav>

    <form method="get" class="filters">
        @if ($stage !== \App\Enums\LeadStage::All)<input type="hidden" name="stage" value="{{ $stage->value }}">@endif
        <div class="search-field">
            <x-icon name="search"/>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by name, phone, email or company">
        </div>
        <select name="status_id" aria-label="Status">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->id }}" @selected(($filters['status_id'] ?? '') == $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>
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
            <a href="{{ route('leads.index', array_filter(['stage' => $stageParam($stage)])) }}" class="btn ghost">Clear</a>
        @endif
    </form>

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
            <button type="submit" class="btn small primary" onclick="return this.form.operation.value !== 'delete' || confirm('Delete the selected leads?')">Apply</button>
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
            @else
                <x-empty icon="search" title="No leads here" text="Nothing matches this view right now."/>
            @endif
        @else
            <div class="scroll-x">
                <table>
                    <thead>
                    <tr>
                        <th class="check"><input type="checkbox" id="select-all" aria-label="Select all on this page"></th>
                        <th>Lead</th><th>Status</th><th>Owner</th><th class="hide-sm">Source</th><th>Next follow-up</th><th class="hide-sm">Added</th>
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
                            <td>
                                @if ($lead->assignee)
                                    <span class="person"><x-avatar :name="$lead->assignee->name" size="sm"/>{{ Str::before($lead->assignee->name, ' ') }}</span>
                                @else
                                    <span class="faint">Unassigned</span>
                                @endif
                            </td>
                            <td class="hide-sm muted">{{ $lead->source?->name ?? '—' }}</td>
                            <td><span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span></td>
                            <td class="hide-sm muted nowrap" title="{{ $lead->created_at->local()->format('d M Y, H:i') }}">{{ $lead->created_at->diffForHumans(short: true) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>

    {{ $leads->links() }}
@endsection

@push('scripts')
<script>
    (function () {
        const checks = () => [...document.querySelectorAll('.row-check')];
        const update = () => {
            const n = checks().filter((c) => c.checked).length;
            document.getElementById('selected-count').textContent = `${n} selected`;
            document.getElementById('bulk-bar').hidden = n === 0;
        };
        document.getElementById('select-all')?.addEventListener('change', (e) => { checks().forEach((c) => c.checked = e.target.checked); update(); });
        checks().forEach((c) => c.addEventListener('change', update));
    })();
</script>
@endpush
