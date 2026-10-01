@extends('layouts.app')
@section('title', 'Platform')
@section('subtitle', 'Every workspace on '.config('app.name').'.')

@section('content')
    <section class="kpis">
        <div class="kpi"><span class="kpi-label">Workspaces<span class="kpi-icon"><x-icon name="building"/></span></span><strong>{{ number_format($stats['workspaces']) }}</strong></div>
        <div class="kpi"><span class="kpi-label">On trial<span class="kpi-icon warn"><x-icon name="clock"/></span></span><strong>{{ number_format($stats['trials']) }}</strong></div>
        <div class="kpi"><span class="kpi-label">Paying<span class="kpi-icon ok"><x-icon name="check-circle"/></span></span><strong>{{ number_format($stats['paying']) }}</strong></div>
        <div class="kpi"><span class="kpi-label">Monthly revenue<span class="kpi-icon ok"><x-icon name="trend"/></span></span><strong>{{ \App\Support\Money::full($stats['mrr']) }}</strong><span class="sub">excl. GST</span></div>
        <div class="kpi"><span class="kpi-label">Leads stored<span class="kpi-icon violet"><x-icon name="leads"/></span></span><strong>{{ number_format($stats['leads']) }}</strong><span class="sub">{{ $stats['suspended'] }} suspended workspaces</span></div>
    </section>

    @php($bad = collect($health)->where('status', 'bad')->count())
    @php($warn = collect($health)->where('status', 'warn')->count())
    <details class="card" @if ($bad) open @endif>
        <summary class="row-between" style="list-style:none">
            <span class="row"><span class="kpi-icon {{ $bad ? '' : ($warn ? 'warn' : 'ok') }}" @if ($bad) style="background:var(--danger-50);color:var(--danger)" @endif><x-icon name="{{ $bad ? 'x' : 'check-circle' }}"/></span>
                <span><strong>System health</strong><span class="muted small" style="display:block">{{ $bad ? "{$bad} problem(s) need attention" : ($warn ? "All critical checks pass · {$warn} to improve" : 'Everything looks good') }}</span></span></span>
            <span class="btn ghost small">Details</span>
        </summary>
        <table class="mt">
            <tbody>
            @foreach ($health as $check)
                <tr>
                    <td style="width:28px">{!! ['ok' => '<span style="color:var(--success)">✓</span>', 'warn' => '<span style="color:var(--warning)">!</span>', 'bad' => '<span style="color:var(--danger)">✗</span>'][$check['status']] !!}</td>
                    <td class="nowrap"><strong>{{ $check['label'] }}</strong></td>
                    <td class="muted">{{ $check['detail'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </details>

    <form method="get" class="filters">
        <div class="search-field"><x-icon name="search"/><input type="search" name="q" value="{{ request('q') }}" placeholder="Search workspaces"></div>
        <button type="submit" class="btn">Search</button>
    </form>

    <section class="card flush">
        <div class="scroll-x">
            <table>
                <thead><tr><th>Workspace</th><th>Plan</th><th>Status</th><th class="num">Users</th><th class="num">Leads</th><th>Joined</th></tr></thead>
                <tbody>
                @forelse ($organizations as $organization)
                    <tr>
                        <td><a href="{{ route('platform.show', $organization) }}" class="cell-main"><x-avatar :name="$organization->name" square/><strong>{{ $organization->name }}</strong></a></td>
                        <td>{{ $organization->plan()->name }}</td>
                        <td>@include('platform._status', ['organization' => $organization])</td>
                        <td class="num">{{ $organization->users_count }}</td>
                        <td class="num">{{ number_format($organization->leads_count) }}</td>
                        <td class="muted nowrap">{{ $organization->created_at->local()->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No workspaces found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $organizations->links() }}
@endsection
