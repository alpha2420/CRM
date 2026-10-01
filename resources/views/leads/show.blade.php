@extends('layouts.app')
@section('title', $lead->name)

@section('header')
    @php($pipeline = $statuses->where('type', \App\Enums\StatusType::Open)->values()->push($statuses->firstWhere('type', \App\Enums\StatusType::Won))->filter()->values())
    @php($current = $pipeline->search(fn ($s) => $s->id === $lead->status_id))
    @php($statusType = $lead->status?->type)
    <section class="card">
        <div class="row-between" style="align-items:flex-start; flex-wrap: wrap">
            <div class="lead-head">
                <x-avatar :name="$lead->name" size="lg"/>
                <div>
                    <h1>{{ $lead->name }} @include('partials.status', ['status' => $lead->status]) @if ($lead->priority === \App\Enums\Priority::High)<span class="flag"><x-icon name="flag" class="icon sm"/>High priority</span>@endif</h1>
                    <div class="lead-meta">
                        @if ($lead->company)<span><x-icon name="building" class="icon sm"/>{{ $lead->company }}</span>@endif
                        <span><x-icon name="phone" class="icon sm"/>{{ $lead->phone }}</span>
                        @if ($lead->source)<span><x-icon name="tag" class="icon sm"/>{{ $lead->source->name }}</span>@endif
                        <span><x-icon name="user" class="icon sm"/>{{ $lead->assignee?->name ?? 'Unassigned' }}</span>
                        <span><x-icon name="calendar" class="icon sm"/>Added {{ $lead->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
            <div class="actions">
                <a href="tel:{{ $lead->phone }}" class="btn"><x-icon name="phone"/>Call</a>
                @if ($whatsappEnabled)<a href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']) }}" class="btn"><x-icon name="whatsapp"/>WhatsApp</a>@endif
                @if ($lead->email)<a href="mailto:{{ $lead->email }}" class="btn hide-sm"><x-icon name="mail"/>Email</a>@endif
                @can('update', $lead)<a href="{{ route('leads.edit', $lead) }}" class="btn"><x-icon name="edit"/>Edit</a>@endcan
                @can('delete', $lead)
                    <details class="dropdown">
                        <summary class="btn" aria-label="More actions"><x-icon name="more"/></summary>
                        <div class="menu">
                            <form method="post" action="{{ route('leads.destroy', $lead) }}" data-confirm="Delete this lead and its history?">
                                @csrf @method('delete')
                                <button type="submit" class="danger"><x-icon name="trash" class="icon sm"/>Delete lead</button>
                            </form>
                        </div>
                    </details>
                @endcan
            </div>
        </div>
        @if ($pipeline->isNotEmpty())
            <div @class(['stepper', 'won' => $statusType === \App\Enums\StatusType::Won, 'lost' => $statusType === \App\Enums\StatusType::Lost]) aria-label="Pipeline progress">
                @foreach ($pipeline as $i => $step)
                    <span @class(['done' => $current !== false && $i < $current, 'current' => $current === $i]) title="{{ $step->name }}">{{ $step->name }}</span>
                @endforeach
            </div>
            @if ($statusType === \App\Enums\StatusType::Lost)<p class="muted small" style="margin:8px 0 0">This lead was marked {{ $lead->status->name }}{{ $lead->closed_at ? ' on '.$lead->closed_at->format('d M Y') : '' }}.</p>@endif
        @endif
    </section>
@endsection

@section('content')
    <div class="grid-main">
        <div>
            @if ($whatsappEnabled)
                <nav class="tabs">
                    <a href="{{ route('leads.show', $lead) }}" @class(['active' => $tab === 'activity'])><x-icon name="note" class="icon sm"/>Activity</a>
                    <a href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']) }}" @class(['active' => $tab === 'whatsapp'])><x-icon name="whatsapp" class="icon sm"/>WhatsApp @if (($unreadChats ?? 0) > 0)<span class="count">{{ $unreadChats }}</span>@endif</a>
                </nav>
            @endif

            @if ($tab === 'whatsapp')
                @include('leads._whatsapp', ['context' => 'lead'])
            @else
                @can('update', $lead)
                    <section class="card">
                        <div class="card-head"><h2>Log a follow-up</h2></div>
                        <form method="post" action="{{ route('leads.activities.store', $lead) }}" class="stack">
                            @csrf
                            <div>
                                <div class="hint" style="margin-bottom:8px">Outcome</div>
                                <div class="chips" role="radiogroup" aria-label="Outcome">
                                    @foreach ($statuses as $status)
                                        <label class="chip" style="--c: {{ $status->color }}">
                                            <input type="radio" name="status_id" value="{{ $status->id }}" @checked(old('status_id', $lead->status_id) == $status->id) required>
                                            <span>{{ $status->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <textarea name="note" rows="2" maxlength="5000" placeholder="What happened? e.g. Called, interested, wants pricing by Friday">{{ old('note') }}</textarea>
                            <div class="row-between" style="flex-wrap: wrap">
                                <div class="follow-up-row">
                                    <label class="inline">Next follow-up <input type="datetime-local" name="next_follow_up_at" id="next-follow-up" value="{{ old('next_follow_up_at') }}"></label>
                                    <span class="quick-dates">
                                        <button type="button" class="btn small" data-days="1">Tomorrow</button>
                                        <button type="button" class="btn small" data-days="3">In 3 days</button>
                                        <button type="button" class="btn small" data-days="7">Next week</button>
                                    </span>
                                </div>
                                <button type="submit" class="btn primary">Save follow-up</button>
                            </div>
                        </form>
                    </section>
                @endcan

                <section class="card">
                    <div class="card-head"><h2>History</h2><span class="muted small">{{ $lead->activities->count() }} {{ Str::plural('entry', $lead->activities->count()) }}</span></div>
                    <ol class="timeline">
                        @foreach ($lead->activities as $activity)
                            <li>
                                <span class="t-icon"><x-icon :name="$activity->user ? 'note' : 'zap'"/></span>
                                <div class="timeline-head">
                                    <strong>{{ $activity->user?->name ?? 'Automated' }}</strong>
                                    @include('partials.status', ['status' => $activity->status])
                                    <span class="muted" title="{{ $activity->created_at->format('d M Y, H:i') }}">{{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($activity->note)<p>{{ $activity->note }}</p>@endif
                                @if ($activity->next_follow_up_at)<p class="muted small meta"><x-icon name="clock" class="icon sm"/>Next follow-up {{ $activity->next_follow_up_at->format('d M Y, H:i') }}</p>@endif
                            </li>
                        @endforeach
                        <li>
                            <span class="t-icon"><x-icon name="plus"/></span>
                            <div class="timeline-head"><span class="muted">Lead added{{ $lead->creator ? ' by '.$lead->creator->name : '' }}{{ $lead->source ? ' from '.$lead->source->name : '' }} · {{ $lead->created_at->format('d M Y, H:i') }}</span></div>
                        </li>
                    </ol>
                </section>
            @endif
        </div>

        <aside>
            @if ($aiEnabled && ($aiAvailable || $lead->ai_insight))
                @include('leads._ai')
            @endif
            <section class="card">
                <div class="card-head"><h2>About</h2>@can('update', $lead)<a href="{{ route('leads.edit', $lead) }}" class="btn ghost small">Edit</a>@endcan</div>
                <div class="section-label">Contact</div>
                <dl class="details">
                    <dt>Phone</dt><dd><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></dd>
                    <dt>Email</dt><dd>@if ($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else <span class="faint">—</span> @endif</dd>
                    <dt>Company</dt><dd>{{ $lead->company ?? '—' }}</dd>
                    <dt>City</dt><dd>{{ $lead->city ?? '—' }}</dd>
                </dl>
                <div class="section-label">Deal</div>
                <dl class="details">
                    <dt>Value</dt><dd>{{ $lead->value !== null ? '₹'.number_format((float) $lead->value) : '—' }}</dd>
                    <dt>Priority</dt><dd><span class="priority {{ $lead->priority->value }}">{{ $lead->priority->label() }}</span></dd>
                    <dt>Owner</dt><dd>@if ($lead->assignee)<span class="person"><x-avatar :name="$lead->assignee->name" size="sm"/>{{ $lead->assignee->name }}</span>@else Unassigned @endif</dd>
                    @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                    <dt>Follow-up</dt><dd><span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span></dd>
                </dl>
                @if ($customFields->isNotEmpty())
                    <div class="section-label">Details</div>
                    <dl class="details">
                        @foreach ($customFields as $field)
                            <dt>{{ $field->label }}</dt><dd>{{ $field->display($lead->custom_values[$field->key] ?? null) }}</dd>
                        @endforeach
                    </dl>
                @endif
                @if ($lead->notes)
                    <div class="section-label">Notes</div>
                    <p class="pre" style="margin:0">{{ $lead->notes }}</p>
                @endif
            </section>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
    // Quick dates fill "next follow-up" with a day offset at 11:00.
    document.querySelectorAll('[data-days]').forEach((button) => button.addEventListener('click', () => {
        const d = new Date();
        d.setDate(d.getDate() + Number(button.dataset.days));
        d.setHours(11, 0, 0, 0);
        const pad = (n) => String(n).padStart(2, '0');
        document.getElementById('next-follow-up').value =
            `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }));
</script>
@endpush
