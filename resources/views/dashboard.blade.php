@extends('layouts.app')
@php($hour = now()->hour)
@section('title', ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening')).', '.Str::before(auth()->user()->name, ' '))
@section('subtitle', now()->format('l, d F').' · here is what needs your attention.')
@section('actions')
    <a href="{{ route('leads.create') }}" class="btn primary"><x-icon name="plus"/>Add lead</a>
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

    <section class="kpis">
        <a @class(['kpi', 'alert-tile' => $stats['due'] > 0]) href="{{ route('leads.index', ['stage' => 'due']) }}">
            <span class="kpi-label"><span class="kpi-icon warn"><x-icon name="clock"/></span>Follow-ups due</span>
            <strong>{{ number_format($stats['due']) }}</strong>
            <span class="sub">{{ $stats['due'] ? 'Today and overdue' : 'Nothing waiting' }}</span>
        </a>
        <div class="kpi">
            <span class="kpi-label"><span class="kpi-icon"><x-icon name="plus"/></span>New today</span>
            <strong>{{ number_format($stats['new_today']) }}</strong>
            <span class="sub">{{ number_format($stats['total']) }} leads in total</span>
        </div>
        <a class="kpi" href="{{ route('leads.index', ['stage' => 'working']) }}">
            <span class="kpi-label"><span class="kpi-icon violet"><x-icon name="target"/></span>Open pipeline</span>
            <strong>{{ number_format($stats['open']) }}</strong>
            <span class="sub">{{ number_format($stats['dormant']) }} dormant</span>
        </a>
        <a class="kpi" href="{{ route('leads.index', ['stage' => 'won']) }}">
            <span class="kpi-label"><span class="kpi-icon ok"><x-icon name="trend"/></span>Won</span>
            <strong>{{ number_format($stats['won']) }}</strong>
            <span class="sub">{{ $stats['won_this_month'] }} this month · {{ $stats['conversion'] }}% conversion</span>
        </a>
    </section>

    <div class="grid-main">
        <section class="card flush">
            <div class="card-head">
                <div><h2>Follow-ups due</h2><p class="muted small">Today and overdue, oldest first</p></div>
                <a href="{{ route('leads.index', ['stage' => 'due']) }}" class="btn ghost small">View all<x-icon name="chevron-right"/></a>
            </div>
            @if ($stats['upcoming']->isEmpty())
                <x-empty icon="check-circle" title="You're all caught up" text="No follow-ups are due. New ones appear here the moment they are."/>
            @else
                <ul class="agenda">
                    @foreach ($stats['upcoming'] as $lead)
                        @php($due = \App\Support\FollowUp::describe($lead->next_follow_up_at))
                        <li>
                            <a href="{{ route('leads.show', $lead) }}">
                                <x-avatar :name="$lead->name"/>
                                <span class="grow"><strong>{{ $lead->name }}</strong><span class="muted small">{{ $lead->phone }}@if ($lead->assignee) · {{ $lead->assignee->name }}@endif</span></span>
                                @include('partials.status', ['status' => $lead->status])
                                <span class="due {{ $due['tone'] }}">{{ $due['text'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card">
            <div class="card-head"><h2>Pipeline</h2></div>
            @php($max = max(1, $stats['by_status']->max('leads_count')))
            @foreach ($stats['by_status'] as $status)
                <a class="bar-row" href="{{ route('leads.index', ['status_id' => $status->id]) }}">
                    <span class="bar-label"><span class="dot" style="background: {{ $status->color }}"></span>{{ $status->name }}</span>
                    <span class="bar"><span style="width: {{ $status->leads_count / $max * 100 }}%; background: {{ $status->color }}"></span></span>
                    <span class="bar-value">{{ $status->leads_count }}</span>
                </a>
            @endforeach
        </section>
    </div>

    <div class="grid-2 mt">
        <section class="card">
            <div class="card-head"><h2>New leads</h2><span class="muted small">Last 6 months</span></div>
            @php($max = max(1, max($stats['monthly'])))
            @foreach ($stats['monthly'] as $month => $count)
                <div class="bar-row">
                    <span class="bar-label">{{ $month }}</span>
                    <span class="bar"><span style="width: {{ $count / $max * 100 }}%"></span></span>
                    <span class="bar-value">{{ $count }}</span>
                </div>
            @endforeach
        </section>

        <section class="card">
            <div class="card-head"><h2>Top sources</h2>@can('admin')<a href="{{ route('reports') }}" class="btn ghost small">Reports<x-icon name="chevron-right"/></a>@endcan</div>
            @php($max = max(1, $stats['by_source']->max('leads_count')))
            @forelse ($stats['by_source']->take(6) as $source)
                <a class="bar-row" href="{{ route('leads.index', ['source_id' => $source->id]) }}">
                    <span class="bar-label">{{ $source->name }}</span>
                    <span class="bar"><span style="width: {{ $source->leads_count / $max * 100 }}%"></span></span>
                    <span class="bar-value">{{ $source->leads_count }}</span>
                </a>
            @empty
                <p class="muted">No sources yet.</p>
            @endforelse
        </section>
    </div>

    @can('admin')
        <section class="card flush">
            <div class="card-head"><div><h2>Team</h2><p class="muted small">Leads assigned and won per agent</p></div><a href="{{ route('users.index') }}" class="btn ghost small">Manage team<x-icon name="chevron-right"/></a></div>
            @if ($stats['by_agent']->isEmpty())
                <x-empty icon="user-plus" title="Add your first agent" text="New leads are shared between active agents automatically.">
                    <a href="{{ route('users.create') }}" class="btn primary"><x-icon name="user-plus"/>Add agent</a>
                </x-empty>
            @else
                <div class="scroll-x">
                    <table>
                        <thead><tr><th>Agent</th><th class="num">Leads</th><th class="num">Won</th><th class="num">Win rate</th></tr></thead>
                        <tbody>
                        @foreach ($stats['by_agent'] as $agent)
                            <tr>
                                <td><a href="{{ route('leads.index', ['assigned_to' => $agent->id]) }}" class="person"><x-avatar :name="$agent->name" size="sm"/>{{ $agent->name }}</a> @unless ($agent->is_active)<span class="pill">Inactive</span>@endunless</td>
                                <td class="num">{{ $agent->assigned_leads_count }}</td>
                                <td class="num">{{ $agent->won_count }}</td>
                                <td class="num">{{ $agent->assigned_leads_count ? round($agent->won_count / $agent->assigned_leads_count * 100) : 0 }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endcan
@endsection
