@extends('layouts.app')
@php($hour = \App\Support\LocalTime::now()->hour)
@section('title', ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening')).', '.Str::before(auth()->user()->name, ' '))
@section('subtitle')
    {{ \App\Support\LocalTime::now()->format('l, j F') }} ·
    @if ($stats['overdue'])
        <strong class="tone-hot">{{ $stats['overdue'] }} {{ Str::plural('follow-up', $stats['overdue']) }} overdue</strong>
    @elseif ($stats['due'])
        {{ $stats['due'] }} {{ Str::plural('follow-up', $stats['due']) }} due today
    @else
        nothing is waiting on you
    @endif
@endsection
@section('actions')
    @can('admin')<a href="{{ route('leads.import') }}" class="btn"><x-icon name="upload"/>Import leads</a>@endcan
@endsection

@section('content')
    @if ($onboarding)
        @php($done = collect($onboarding)->where('done', true)->count())
        <section class="card onboarding">
            <div class="row-between">
                <div>
                    <h2>Get your workspace ready</h2>
                    <p class="muted small" style="margin:2px 0 0">{{ $done }} of {{ count($onboarding) }} done · takes about 5 minutes</p>
                </div>
                <form method="post" action="{{ route('onboarding.dismiss') }}">
                    @csrf
                    <button type="submit" class="btn ghost small">Dismiss</button>
                </form>
            </div>
            <div class="meter mt"><span style="width: {{ $done / count($onboarding) * 100 }}%"></span></div>
            <div class="onboarding-steps">
                @foreach ($onboarding as $step)
                    <a href="{{ $step['url'] }}" @class(['step', 'done' => $step['done']])>
                        <span class="tick">@if ($step['done'])<x-icon name="check" class="icon sm"/>@endif</span>
                        <span><strong>{{ $step['title'] }}</strong><span>{{ $step['text'] }}</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @php($weekDelta = $stats['new_week'] - $stats['new_previous_week'])
    <section class="kpis">
        <a class="kpi" href="{{ route('leads.index', ['from' => \App\Support\LocalTime::now()->subDays(6)->toDateString()]) }}">
            <span class="kpi-label">New leads this week<span class="kpi-icon"><x-icon name="user-plus"/></span></span>
            <strong>{{ number_format($stats['new_week']) }}</strong>
            <span class="sub"><b @class(['tone-ok' => $weekDelta > 0, 'tone-hot' => $weekDelta < 0])>{{ $weekDelta > 0 ? '+' : '' }}{{ $weekDelta }}</b> vs last week · {{ $stats['new_today'] }} today</span>
        </a>
        <a @class(['kpi', 'alert-tile' => $stats['overdue'] > 0]) href="{{ route('leads.index', ['stage' => 'due']) }}">
            <span class="kpi-label">Follow-ups due<span class="kpi-icon hot"><x-icon name="clock"/></span></span>
            <strong>{{ number_format($stats['due']) }}</strong>
            <span class="sub">@if ($stats['overdue'])<b class="tone-hot">{{ $stats['overdue'] }} overdue</b> · {{ $stats['due'] - $stats['overdue'] }} later today @else {{ $stats['due'] ? 'All due later today' : 'Nothing waiting' }} @endif</span>
        </a>
        <a class="kpi" href="{{ route('leads.index', ['view' => 'board']) }}">
            <span class="kpi-label">Open pipeline<span class="kpi-icon violet"><x-icon name="trend"/></span></span>
            <strong>{{ \App\Support\Money::short($stats['open_value']) }}</strong>
            <span class="sub">{{ number_format($stats['open']) }} open {{ Str::plural('lead', $stats['open']) }} · {{ number_format($stats['dormant']) }} dormant</span>
        </a>
        <a class="kpi" href="{{ route('leads.index', ['stage' => 'won']) }}">
            <span class="kpi-label">Won this month<span class="kpi-icon ok"><x-icon name="target"/></span></span>
            <strong>{{ number_format($stats['won_this_month']) }}</strong>
            <span class="sub">{{ \App\Support\Money::short($stats['won_value_this_month']) }} closed · {{ $stats['conversion'] }}% win rate</span>
        </a>
    </section>

    <div class="grid-main dash-grid">
        <div class="col-stack">
            <section class="card flush">
                <div class="card-head">
                    <div><h2>Today's follow-ups</h2><p class="muted small">Call or message them, then log what happened.</p></div>
                    <a href="{{ route('leads.index', ['stage' => 'due']) }}" class="card-link">All follow-ups<x-icon name="chevron-right"/></a>
                </div>
                @if ($stats['upcoming']->isEmpty())
                    <x-empty icon="check-circle" title="You're all caught up" text="No follow-ups are due. New ones appear here the moment they are."/>
                @else
                    <ul class="agenda">
                        @foreach ($stats['upcoming'] as $lead)
                            @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                            <li>
                                <a href="{{ route('leads.show', $lead) }}" class="who">
                                    <x-avatar :name="$lead->name"/>
                                    <span class="grow"><strong>{{ $lead->name }}</strong><span class="muted small">{{ $lead->company ?: $lead->phone }}@if (auth()->user()->isAdmin() && $lead->assignee) · {{ Str::before($lead->assignee->name, ' ') }}@endif</span></span>
                                </a>
                                <span class="meta">
                                    <span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span>
                                    @include('partials.status', ['status' => $lead->status])
                                    @include('partials.contact-tools')
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card">
                <div class="card-head">
                    <div><h2>Pipeline</h2><p class="muted small">Leads and deal value in each stage</p></div>
                    <a href="{{ route('leads.index', ['view' => 'board']) }}" class="card-link">Open board<x-icon name="chevron-right"/></a>
                </div>
                @php($max = max(1, $stats['by_status']->max('leads_count')))
                @foreach ($stats['by_status'] as $status)
                    <a class="bar-row wide" href="{{ route('leads.index', ['status_id' => $status->id]) }}">
                        <span class="bar-label"><span class="dot" style="background: {{ $status->color }}"></span>{{ $status->name }}</span>
                        <span class="bar"><span style="width: {{ $status->leads_count / $max * 100 }}%; background: {{ $status->color }}"></span></span>
                        <span class="bar-value">{{ $status->leads_count }}</span>
                        <span class="bar-extra">{{ $status->leads_sum_value ? \App\Support\Money::short($status->leads_sum_value) : '—' }}</span>
                    </a>
                @endforeach
            </section>
        </div>

        <div class="col-stack">
            @if ($nav['inbox'])
                <section class="card">
                    <div class="card-head"><h2>Unread messages</h2><a href="{{ route('inbox') }}" class="card-link">Inbox<x-icon name="chevron-right"/></a></div>
                    @if ($stats['chats']->isEmpty())
                        <p class="muted" style="margin:0">No unread WhatsApp messages.</p>
                    @else
                        <ul class="mini-list">
                            @foreach ($stats['chats'] as $chat)
                                <li>
                                    <a href="{{ route('inbox', ['lead' => $chat->id]) }}">
                                        <x-avatar :name="$chat->name"/>
                                        <span class="grow"><strong>{{ $chat->name }}</strong><span>{{ $chat->latestWhatsAppMessage?->body }}</span></span>
                                        <span class="end">{{ $chat->last_message_at?->diffForHumans(short: true) }}<span class="unread-dot"></span></span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section class="card">
                <div class="card-head"><div><h2>Where leads come from</h2><p class="muted small">Last 30 days</p></div>@can('admin')<a href="{{ route('reports') }}" class="card-link">Reports<x-icon name="chevron-right"/></a>@endcan</div>
                @php($sourceTotal = max(1, $stats['by_source']->sum('leads_count')))
                @php($sourceMax = max(1, $stats['by_source']->max('leads_count')))
                @forelse ($stats['by_source']->take(5) as $source)
                    <a class="share" href="{{ route('leads.index', ['source_id' => $source->id]) }}">
                        <span class="share-top"><span>{{ $source->name }}</span><strong>{{ round($source->leads_count / $sourceTotal * 100) }}%</strong></span>
                        <span class="bar"><span style="width: {{ $source->leads_count / $sourceMax * 100 }}%"></span></span>
                    </a>
                @empty
                    <p class="muted" style="margin:0">No new leads in the last 30 days.</p>
                @endforelse
            </section>

            @can('admin')
                <section class="card">
                    <div class="card-head"><h2>Team</h2><a href="{{ route('users.index') }}" class="card-link">Manage<x-icon name="chevron-right"/></a></div>
                    @if ($stats['by_agent']->isEmpty())
                        <x-empty icon="user-plus" title="Invite your first agent" text="New leads are shared between active agents automatically." style="padding:12px 0">
                            <a href="{{ route('users.index') }}" class="btn primary small"><x-icon name="user-plus"/>Invite</a>
                        </x-empty>
                    @else
                        <ul class="mini-list">
                            @foreach ($stats['by_agent'] as $agent)
                                <li>
                                    <a href="{{ route('leads.index', ['assigned_to' => $agent->id]) }}">
                                        <x-avatar :name="$agent->name"/>
                                        <span class="grow"><strong>{{ $agent->name }}</strong><span>{{ $agent->open_count }} open @unless ($agent->is_active)· inactive @endunless</span></span>
                                        <span class="pill ok">{{ $agent->won_count }} won</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endcan
        </div>
    </div>
@endsection
