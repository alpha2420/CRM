@extends('layouts.app')
@section('title', 'Settings')
@section('subtitle', 'Manage your workspace, team, pipeline and connections.')

@section('content')
    @php($groups = [
        'Workspace' => [
            ['settings.organization.edit', 'settings.organization.*', 'General', 'building'],
            ['users.index', 'users.*', 'Team', 'users'],
            ['settings.billing', 'settings.billing*', 'Billing', 'card'],
        ],
        'Sales process' => [
            ['settings.statuses.index', 'settings.statuses.*', 'Pipeline stages', 'layers'],
            ['settings.routing.index', 'settings.routing.*', 'Lead routing', 'route'],
            ['settings.sources.index', 'settings.sources.*', 'Lead sources', 'tag'],
            ['settings.lost-reasons.index', 'settings.lost-reasons.*', 'Lost reasons', 'x'],
            ['settings.custom-fields.index', 'settings.custom-fields.*', 'Custom fields', 'sliders'],
            ['settings.autopilot.edit', 'settings.autopilot.*', 'Autopilot', 'autopilot'],
            ['settings.sequences.index', 'settings.sequences.*', 'Sequences', 'send'],
            ['settings.automations.index', 'settings.automations.*', 'Automations', 'zap'],
        ],
        'Connections' => [
            ['settings.integrations.index', 'settings.integrations.*', 'Integrations', 'plug'],
            ['settings.webhooks.index', 'settings.webhooks.*', 'Webhooks', 'code'],
            ['leads.import', 'leads.import', 'Import & export', 'upload'],
        ],
        'Security' => [
            ['settings.activity', 'settings.activity', 'Activity log', 'activity'],
        ],
    ])
    <div class="settings">
        <nav class="settings-nav" aria-label="Settings">
            @foreach ($groups as $group => $links)
                <div class="group">{{ $group }}</div>
                @foreach ($links as [$route, $pattern, $label, $icon])
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])><x-icon :name="$icon"/>{{ $label }}</a>
                @endforeach
            @endforeach
        </nav>
        <div class="settings-body">@yield('settings')</div>
    </div>
@endsection
