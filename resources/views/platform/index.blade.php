@extends('layouts.app')
@section('title', 'Platform')
@section('subtitle', 'Every workspace on '.config('app.name').'.')

@section('content')
    <section class="kpis">
        <div class="kpi"><span class="kpi-label"><span class="kpi-icon"><x-icon name="building"/></span>Workspaces</span><strong>{{ number_format($stats['workspaces']) }}</strong></div>
        <div class="kpi"><span class="kpi-label"><span class="kpi-icon warn"><x-icon name="clock"/></span>On trial</span><strong>{{ number_format($stats['trials']) }}</strong></div>
        <div class="kpi"><span class="kpi-label"><span class="kpi-icon ok"><x-icon name="check-circle"/></span>Paying</span><strong>{{ number_format($stats['paying']) }}</strong></div>
        <div class="kpi"><span class="kpi-label"><span class="kpi-icon ok"><x-icon name="trend"/></span>Monthly revenue</span><strong>₹{{ number_format($stats['mrr']) }}</strong><span class="sub">excl. GST</span></div>
        <div class="kpi"><span class="kpi-label"><span class="kpi-icon violet"><x-icon name="leads"/></span>Leads stored</span><strong>{{ number_format($stats['leads']) }}</strong><span class="sub">{{ $stats['suspended'] }} suspended workspaces</span></div>
    </section>

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
                        <td class="muted nowrap">{{ $organization->created_at->format('d M Y') }}</td>
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
