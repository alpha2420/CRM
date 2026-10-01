@extends('layouts.app')
@section('title', 'Settings')
@section('subtitle', 'Manage your workspace, team, pipeline and connections.')

@section('content')
    @php($tabs = [
        ['settings.organization.edit', 'settings.organization.*', 'Workspace', 'building'],
        ['users.index', 'users.*', 'Team', 'users'],
        ['settings.statuses.index', 'settings.statuses.*', 'Pipeline', 'layers'],
        ['settings.sources.index', 'settings.sources.*', 'Lead sources', 'tag'],
        ['settings.custom-fields.index', 'settings.custom-fields.*', 'Custom fields', 'sliders'],
        ['settings.automations.index', 'settings.automations.*', 'Automations', 'zap'],
        ['settings.integrations.index', 'settings.integrations.*', 'Integrations', 'plug'],
        ['leads.import', 'leads.import', 'Import / Export', 'upload'],
        ['settings.billing', 'settings.billing*', 'Billing', 'card'],
    ])
    <div class="settings">
        <nav class="settings-nav">
            @foreach ($tabs as [$route, $pattern, $label, $icon])
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])><x-icon :name="$icon"/>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="settings-body">@yield('settings')</div>
    </div>
@endsection
