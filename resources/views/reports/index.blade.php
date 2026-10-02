@extends('layouts.app')
@section('guide', 'reports')
@section('title', 'Reports')
@section('subtitle', $from->format('d M Y').' – '.$to->format('d M Y'))

@section('content')
    @php($kpis = $report['kpis'])

    <form method="get" class="filters">
        <div class="segmented" role="group" aria-label="Date range">
            @foreach ($presets as $key => $label)
                <a href="{{ route('reports', ['range' => $key]) }}" @class(['active' => $range === (string) $key])>{{ $label }}</a>
            @endforeach
        </div>
        <input type="hidden" name="range" value="custom">
        <label class="inline">From <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
        <label class="inline">To <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
        <button type="submit" class="btn">Apply</button>
    </form>

    <section class="kpis six">
        <div class="kpi"><span class="kpi-label">New leads<span class="kpi-icon"><x-icon name="user-plus"/></span></span><strong>{{ number_format($kpis['new']) }}</strong><span class="sub">@include('reports._delta', ['current' => $kpis['new'], 'previous' => $kpis['new_previous']])</span></div>
        <div class="kpi"><span class="kpi-label">Contacted<span class="kpi-icon violet"><x-icon name="phone"/></span></span><strong>{{ $kpis['contacted_rate'] }}%</strong><span class="sub">of new leads</span></div>
        <div class="kpi"><span class="kpi-label">Answered in 5 min<span class="kpi-icon warn"><x-icon name="clock"/></span></span><strong>{{ $kpis['speed']['rate'] }}%</strong><span class="sub">median first reply {{ \App\Support\Duration::seconds($kpis['speed']['median']) }} (working hours)</span></div>
        <div class="kpi"><span class="kpi-label">Won<span class="kpi-icon ok"><x-icon name="target"/></span></span><strong>{{ number_format($kpis['won']) }}</strong><span class="sub">@include('reports._delta', ['current' => $kpis['won'], 'previous' => $kpis['won_previous']])</span></div>
        <div class="kpi"><span class="kpi-label">Win rate<span class="kpi-icon ok"><x-icon name="trend"/></span></span><strong>{{ $kpis['win_rate'] }}%</strong><span class="sub">of new leads</span></div>
        <div class="kpi"><span class="kpi-label">Won value<span class="kpi-icon violet"><x-icon name="card"/></span></span><strong title="{{ \App\Support\Money::full($kpis['won_value']) }}">{{ \App\Support\Money::short($kpis['won_value']) }}</strong><span class="sub">deal value closed</span></div>
    </section>

    <div class="grid-2 report-charts">
        <section class="card">
            <div class="card-head"><div><h2>Funnel</h2><p class="muted small">Leads created in this period and how far they got</p></div></div>
            @php($top = max(1, $report['funnel']['New']))
            <div class="funnel">
                @foreach ($report['funnel'] as $stage => $count)
                    <div class="funnel-row">
                        <span class="funnel-label">{{ $stage }}</span>
                        <span class="funnel-track"><span class="funnel-bar" style="width: {{ max($count ? 1 : 0, $count / $top * 100) }}%"></span></span>
                        <span class="funnel-value"><strong>{{ number_format($count) }}</strong> <span class="muted">{{ $loop->first ? '' : round($count / $top * 100).'%' }}</span></span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>New leads per {{ $report['trend']['unit'] }}</h2></div>
            @php($points = $report['trend']['points'])
            @php($peak = max(1, collect($points)->max('count')))
            <div class="columns-chart" style="--n: {{ count($points) }}">
                <span class="axis-max muted small">{{ number_format($peak) }}</span>
                <div class="columns" role="list">
                    @foreach ($points as $point)
                        <span class="col" role="listitem" tabindex="0" aria-label="{{ $point['label'] }}: {{ $point['count'] }} leads">
                            <span class="col-bar" style="height: {{ $point['count'] / $peak * 100 }}%"></span>
                            <span class="tip"><strong>{{ number_format($point['count']) }}</strong> {{ $point['label'] }}</span>
                        </span>
                    @endforeach
                </div>
                <div class="columns-axis muted small">
                    <span>{{ $points[0]['date'] ?? '' }}</span>
                    <span>{{ $points[intdiv(count($points), 2)]['date'] ?? '' }}</span>
                    <span>{{ end($points)['date'] ?? '' }}</span>
                </div>
            </div>
            <details class="table-view">
                <summary>View as table</summary>
                <table>
                    <thead><tr><th>{{ ucfirst($report['trend']['unit']) }}</th><th class="num">New leads</th></tr></thead>
                    <tbody>@foreach ($points as $point)<tr><td>{{ $point['label'] }}</td><td class="num">{{ $point['count'] }}</td></tr>@endforeach</tbody>
                </table>
            </details>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><div><h2>Why leads were lost</h2><p class="muted small">Leads marked lost in this period</p></div>@can('admin')<a href="{{ route('settings.lost-reasons.index') }}" class="card-link">Lost reasons<x-icon name="chevron-right"/></a>@endcan</div>
        @php($lostMax = max(1, collect($report['lost_reasons'])->max('leads')))
        @forelse ($report['lost_reasons'] as $row)
            <div class="bar-row">
                <span class="bar-label">{{ $row['name'] }}</span>
                <span class="bar"><span style="width: {{ $row['leads'] / $lostMax * 100 }}%; background: var(--danger-dot)"></span></span>
                <span class="bar-value">{{ $row['leads'] }}</span>
            </div>
        @empty
            <p class="muted" style="margin:0">No leads were lost in this period.</p>
        @endforelse
    </section>

    <section class="card flush">
        <div class="card-head"><h2>Sources</h2><span class="muted small">Leads created in this period</span></div>
        <div class="scroll-x"><table>
            <thead><tr><th>Source</th><th class="num">Leads</th><th class="num">Contacted</th><th class="num">Won</th><th class="num">Win rate</th><th class="num">Won value</th></tr></thead>
            <tbody>
            @forelse ($report['sources'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['leads']) }}</td>
                    <td class="num">{{ $row['contacted'] }}%</td>
                    <td class="num">{{ number_format($row['won']) }}</td>
                    <td class="num">{{ $row['win_rate'] }}%</td>
                    <td class="num">{{ \App\Support\Money::full($row['won_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No leads in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card flush">
        <div class="card-head"><h2>Campaigns & ads</h2><span class="muted small">Which ads and campaigns bring leads that buy</span></div>
        <div class="scroll-x"><table>
            <thead><tr><th>Campaign</th><th class="num">Leads</th><th class="num">Contacted</th><th class="num">Won</th><th class="num">Win rate</th><th class="num">Won value</th></tr></thead>
            <tbody>
            @forelse ($report['campaigns'] as $row)
                <tr>
                    <td><a href="{{ route('leads.index', ['campaign' => $row['name']]) }}">{{ $row['name'] }}</a></td>
                    <td class="num">{{ number_format($row['leads']) }}</td>
                    <td class="num">{{ $row['contacted'] }}%</td>
                    <td class="num">{{ number_format($row['won']) }}</td>
                    <td class="num">{{ $row['win_rate'] }}%</td>
                    <td class="num">{{ \App\Support\Money::full($row['won_value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No campaign leads in this period. Leads from Click-to-WhatsApp ads, Facebook and Google lead forms are tracked on their own; for your web form, share the link with <code>?utm_campaign=name</code> at the end.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card flush">
        <div class="card-head"><h2>Team</h2><span class="muted small">Speed and results per person</span></div>
        <div class="scroll-x"><table>
            <thead><tr><th>Person</th><th class="num">Leads</th><th class="num">Follow-ups logged</th><th class="num">Calls</th><th class="num">Reached</th><th class="num">Median first reply</th><th class="num">In 5 min</th><th class="num">Won</th><th class="num">Win rate</th></tr></thead>
            <tbody>
            @forelse ($report['agents'] as $row)
                <tr @class(['muted' => ! $row['active']])>
                    <td><span class="person"><x-avatar :name="$row['name']" size="sm"/>{{ $row['name'] }}</span></td>
                    <td class="num">{{ number_format($row['leads']) }}</td>
                    <td class="num">{{ number_format($row['follow_ups']) }}</td>
                    <td class="num">{{ number_format($row['calls']) }}</td>
                    <td class="num">{{ $row['calls'] ? $row['reached'].'%' : '—' }}</td>
                    <td class="num">{{ \App\Support\Duration::seconds($row['speed']['median']) }}</td>
                    <td class="num">{{ $row['speed']['arrived'] ? $row['speed']['rate'].'%' : '—' }}</td>
                    <td class="num">{{ number_format($row['won']) }}</td>
                    <td class="num">{{ $row['win_rate'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No team activity in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
