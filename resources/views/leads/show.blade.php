@extends('layouts.app')
@section('title', $lead->name)

@section('header')
    @php($pipeline = $statuses->where('type', \App\Enums\StatusType::Open)->values()->push($statuses->firstWhere('type', \App\Enums\StatusType::Won))->filter()->values())
    @php($current = $pipeline->search(fn ($s) => $s->id === $lead->status_id))
    @php($statusType = $lead->status?->type)
    @php($wonStatus = $statuses->firstWhere('type', \App\Enums\StatusType::Won))
    @php($lostStatuses = $statuses->where('type', \App\Enums\StatusType::Lost))
    @php($canUpdate = auth()->user()->can('update', $lead))

    <a href="{{ route('leads.index') }}" class="back"><x-icon name="arrow-left"/>Leads</a>
    <section class="card lead-hero">
        <div class="lead-head">
            <x-avatar :name="$lead->name" size="lg"/>
            <div class="grow">
                <h1>{{ $lead->name }} @include('partials.status', ['status' => $lead->status]) @include('partials.score', ['score' => $score?->total]) @if ($lead->priority === \App\Enums\Priority::High)<span class="flag"><x-icon name="flag" class="icon sm"/>High priority</span>@endif</h1>
                <div class="lead-meta">
                    @if ($lead->company || $lead->city)<span><x-icon name="building" class="icon sm"/>{{ collect([$lead->company, $lead->city])->filter()->join(' · ') }}</span>@endif
                    <span><x-icon name="calendar" class="icon sm"/>Added {{ $lead->created_at->local()->format('j M Y') }}{{ $lead->source ? ' from '.$lead->source->name : '' }}</span>
                </div>
            </div>
            <div class="actions">
                <a href="tel:{{ $lead->phone }}" class="btn"><x-icon name="phone"/>Call</a>
                @if ($whatsappEnabled)<a href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']) }}" class="btn"><x-icon name="whatsapp"/>WhatsApp</a>@endif
                @if ($lead->email)<a href="mailto:{{ $lead->email }}" class="btn hide-sm"><x-icon name="mail"/>Email</a>@endif
                @if ($canUpdate && $wonStatus && $statusType === \App\Enums\StatusType::Open)
                    <form method="post" action="{{ route('leads.move', $lead) }}">
                        @csrf @method('patch')
                        <input type="hidden" name="status_id" value="{{ $wonStatus->id }}">
                        <button type="submit" class="btn success"><x-icon name="check"/>Mark won</button>
                    </form>
                @endif
                @if ($canUpdate)
                    <details class="dropdown">
                        <summary class="btn square" aria-label="More actions"><x-icon name="more"/></summary>
                        <div class="menu">
                            <a href="{{ route('leads.edit', $lead) }}"><x-icon name="edit" class="icon sm"/>Edit details</a>
                            @foreach ($lostStatuses as $lost)
                                @continue($lost->id === $lead->status_id)
                                <form method="post" action="{{ route('leads.move', $lead) }}">
                                    @csrf @method('patch')
                                    <input type="hidden" name="status_id" value="{{ $lost->id }}">
                                    <button type="submit"><x-icon name="x" class="icon sm"/>Mark as {{ $lost->name }}</button>
                                </form>
                            @endforeach
                            @can('delete', $lead)
                                <form method="post" action="{{ route('leads.destroy', $lead) }}" data-confirm="Delete this lead and its history?">
                                    @csrf @method('delete')
                                    <button type="submit" class="danger"><x-icon name="trash" class="icon sm"/>Delete lead</button>
                                </form>
                            @endcan
                        </div>
                    </details>
                @endif
            </div>
        </div>

        @if ($pipeline->isNotEmpty())
            <div @class(['stepper', 'won' => $statusType === \App\Enums\StatusType::Won, 'lost' => $statusType === \App\Enums\StatusType::Lost]) aria-label="Stage">
                @foreach ($pipeline as $i => $step)
                    @php($state = $current !== false && $i < $current ? 'done' : ($current === $i ? 'current' : null))
                    @if ($canUpdate && $state !== 'current')
                        <form method="post" action="{{ route('leads.move', $lead) }}">
                            @csrf @method('patch')
                            <input type="hidden" name="status_id" value="{{ $step->id }}">
                            <button type="submit" @class([$state]) title="Move to {{ $step->name }}">@if ($state === 'done')<x-icon name="check"/>@endif{{ $step->name }}</button>
                        </form>
                    @else
                        <span @class([$state]) @if ($state === 'current') aria-current="step" @endif>@if ($state === 'done')<x-icon name="check"/>@endif{{ $step->name }}</span>
                    @endif
                @endforeach
            </div>
            @if ($statusType === \App\Enums\StatusType::Lost)<p class="closed-note">Marked {{ $lead->status->name }}{{ $lead->closed_at ? ' on '.$lead->closed_at->local()->format('j M Y') : '' }}. Click a stage to reopen it.</p>@endif
        @endif
    </section>
@endsection

@section('content')
    @php($canUpdate = auth()->user()->can('update', $lead))
    <div class="lead-grid">
        {{-- Left: who this is --}}
        <aside class="col-stack lead-details">
            <section class="card">
                <div class="card-head"><h2>Details</h2>@if ($canUpdate)<a href="{{ route('leads.edit', $lead) }}" class="btn ghost small"><x-icon name="edit"/>Edit</a>@endif</div>
                <ul class="props">
                    <li><x-icon name="phone"/><span><span class="label">Phone</span><span class="value"><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a></span></span></li>
                    <li><x-icon name="mail"/><span><span class="label">Email</span><span class="value">@if ($lead->email)<a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a>@else<span class="faint">Not added</span>@endif</span></span></li>
                    <li><x-icon name="trend"/><span><span class="label">Deal value</span><span class="value">{!! $lead->value !== null ? e(\App\Support\Money::full($lead->value)) : '<span class="faint">Not set</span>' !!}</span></span></li>
                    <li><x-icon name="flag"/><span><span class="label">Priority</span><span class="value priority {{ $lead->priority->value }}">{{ $lead->priority->label() }}</span></span></li>
                    <li><x-icon name="user"/><span><span class="label">Owner</span><span class="value">@if ($lead->assignee)<span class="person"><x-avatar :name="$lead->assignee->name" size="sm"/>{{ $lead->assignee->name }}</span>@else<span class="faint">Unassigned</span>@endif</span></span></li>
                    <li><x-icon name="tag"/><span><span class="label">Source</span><span class="value">{{ $lead->source?->name ?? '—' }}</span></span></li>
                    @if ($lead->company)<li><x-icon name="building"/><span><span class="label">Company</span><span class="value">{{ $lead->company }}</span></span></li>@endif
                    @if ($lead->city)<li><x-icon name="globe"/><span><span class="label">City</span><span class="value">{{ $lead->city }}</span></span></li>@endif
                    @foreach ($customFields as $field)
                        <li><x-icon name="sliders"/><span><span class="label">{{ $field->label }}</span><span class="value">{{ $field->display($lead->custom_values[$field->key] ?? null) }}</span></span></li>
                    @endforeach
                </ul>
                @if ($lead->notes)
                    <div class="section-label">Notes</div>
                    <p class="pre" style="margin:0">{{ $lead->notes }}</p>
                @endif
            </section>
        </aside>

        {{-- Middle: what is happening --}}
        <div class="col-stack lead-main">
            @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
            @if ($statusType === \App\Enums\StatusType::Open)
                <section class="card next-up {{ $due['tone'] }}">
                    <span class="n-icon"><x-icon name="clock"/></span>
                    <div class="grow">
                        @if ($lead->next_follow_up_at)
                            @php($dueToday = $lead->next_follow_up_at->local()->isToday())
                            <strong>{{ match (true) { $dueToday && $lead->next_follow_up_at->isPast() => 'Follow-up due now', $dueToday => 'Follow-up due today at '.$lead->next_follow_up_at->local()->format('H:i'), $due['tone'] === 'overdue' => 'Follow-up overdue', default => 'Next follow-up '.$due['text'] } }}</strong>
                            <span class="muted small">{{ $due['tone'] === 'overdue' ? 'Was due' : 'Planned for' }} {{ $lead->next_follow_up_at->local()->format('l j M Y, H:i') }}</span>
                        @else
                            <strong>No follow-up planned</strong>
                            <span class="muted small">Log what happened and pick the next date, so this lead is not forgotten.</span>
                        @endif
                    </div>
                </section>
            @endif

            @if ($whatsappEnabled)
                <nav class="tabs" style="margin-bottom:0">
                    <a href="{{ route('leads.show', $lead) }}" @class(['active' => $tab === 'activity'])><x-icon name="activity" class="icon sm"/>Activity</a>
                    <a href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']) }}" @class(['active' => $tab === 'whatsapp'])><x-icon name="whatsapp" class="icon sm"/>WhatsApp @if (($unreadChats ?? 0) > 0)<span class="count">{{ $unreadChats }}</span>@endif</a>
                </nav>
            @endif

            @if ($tab === 'whatsapp')
                @include('leads._whatsapp', ['context' => 'lead'])
            @else
                @if ($canUpdate)
                    <section class="card composer-card" id="log">
                        <form method="post" action="{{ route('leads.activities.store', $lead) }}" class="stack">
                            @csrf
                            <div>
                                <h2 style="margin-bottom:10px">Log a follow-up</h2>
                                <div class="chips" role="radiogroup" aria-label="Outcome">
                                    @foreach ($statuses as $status)
                                        <label class="chip" style="--c: {{ $status->color }}">
                                            <input type="radio" name="status_id" value="{{ $status->id }}" @checked(old('status_id', $lead->status_id) == $status->id) required>
                                            <span>{{ $status->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <label class="sr-only" for="activity-note">What happened?</label>
                            <textarea name="note" id="activity-note" rows="2" maxlength="5000" placeholder="What happened? e.g. Called, interested, wants pricing by Friday">{{ old('note') }}</textarea>
                            <div class="composer-foot">
                                <div class="follow-up-row">
                                    <label class="inline" for="next-follow-up">Next follow-up</label>
                                    <input type="datetime-local" name="next_follow_up_at" id="next-follow-up" value="{{ old('next_follow_up_at') }}">
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
                @endif

                <section class="card">
                    <div class="card-head"><h2>History</h2><span class="muted small">{{ $lead->activities->count() }} {{ Str::plural('entry', $lead->activities->count()) }}</span></div>
                    <ol class="timeline">
                        @foreach ($lead->activities as $activity)
                            @php($byAutopilot = ! $activity->user && Str::startsWith((string) $activity->note, 'Autopilot: '))
                            <li>
                                <span @class(['t-icon', 'auto' => ! $activity->user])><x-icon :name="$activity->user ? ($activity->note ? 'note' : 'layers') : ($byAutopilot ? 'autopilot' : 'zap')"/></span>
                                <div class="timeline-head">
                                    <strong>{{ $activity->user?->name ?? ($byAutopilot ? 'Autopilot' : 'Automatic') }}</strong>
                                    @include('partials.status', ['status' => $activity->status])
                                    <span class="when" title="{{ $activity->created_at->local()->format('d M Y, H:i') }}">{{ $activity->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($activity->note)<p>{{ $byAutopilot ? Str::after($activity->note, 'Autopilot: ') : $activity->note }}</p>@endif
                                @if ($activity->next_follow_up_at)<p class="muted small meta"><x-icon name="clock" class="icon sm"/>Next follow-up {{ $activity->next_follow_up_at->local()->format('d M Y, H:i') }}</p>@endif
                            </li>
                        @endforeach
                        <li>
                            <span class="t-icon plain"><x-icon name="plus"/></span>
                            <div class="timeline-head"><span class="muted">Lead added{{ $lead->creator ? ' by '.$lead->creator->name : '' }}{{ $lead->source ? ' from '.$lead->source->name : '' }} · {{ $lead->created_at->local()->format('d M Y, H:i') }}</span></div>
                        </li>
                    </ol>
                </section>
            @endif
        </div>

        {{-- Right: help to move it forward --}}
        <aside class="col-stack lead-side">
            @if ($score)
                @include('leads._score')
            @endif
            @if ($aiEnabled && ($aiAvailable || $lead->ai_insight))
                @include('leads._ai')
            @endif

            @if ($whatsappEnabled && $tab === 'activity')
                @php($lastMessage = $lead->latestWhatsAppMessage)
                <section class="card">
                    <div class="card-head" style="margin-bottom:10px">
                        <h2>WhatsApp</h2>
                        @if ($lead->whatsappWindowOpen())<span class="pill ok">Reply window open</span>@else<span class="pill">Templates only</span>@endif
                    </div>
                    @if ($lastMessage)
                        <div class="ai-step">
                            <div class="pre">{{ Str::limit($lastMessage->body, 160) }}</div>
                            <div class="muted small" style="margin-top:4px">{{ $lastMessage->isInbound() ? Str::before($lead->name, ' ') : 'You' }} · {{ $lastMessage->created_at->diffForHumans() }}</div>
                        </div>
                    @else
                        <p class="muted" style="margin:0">No messages yet.</p>
                    @endif
                    <a href="{{ route('leads.show', ['lead' => $lead, 'tab' => 'whatsapp']) }}" class="btn block mt"><x-icon name="whatsapp"/>{{ $lastMessage ? 'Open chat' : 'Send a message' }}</a>
                </section>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
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
