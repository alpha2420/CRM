@extends('layouts.app')
@section('title', 'Settings')
@section('subtitle', 'Manage your workspace, team, pipeline and connections.')

@section('content')
    @php($everyday = [
        ['settings.organization.edit', ['settings.organization.*'], 'General', 'building'],
        ['users.index', ['users.*'], 'Team', 'users'],
        ['settings.integrations.index', ['settings.integrations.*'], 'Integrations', 'plug'],
        ['settings.autopilot.edit', ['settings.autopilot.*', 'settings.routing.*', 'settings.automations.*', 'settings.sequences.*'], 'Automation', 'autopilot'],
        ['leads.import', ['leads.import'], 'Import & export', 'upload'],
        ['settings.billing', ['settings.billing*'], 'Billing', 'card'],
    ])
    {{-- Set once and rarely changed: kept out of the way. --}}
    @php($advanced = [
        ['settings.statuses.index', ['settings.statuses.*'], 'Pipeline stages', 'layers'],
        ['settings.sources.index', ['settings.sources.*'], 'Lead sources', 'tag'],
        ['settings.lost-reasons.index', ['settings.lost-reasons.*'], 'Lost reasons', 'x'],
        ['settings.custom-fields.index', ['settings.custom-fields.*'], 'Custom fields', 'sliders'],
        ['settings.webhooks.index', ['settings.webhooks.*'], 'Webhooks', 'code'],
        ['settings.privacy.edit', ['settings.privacy.*'], 'Privacy & consent', 'shield'],
        ['settings.activity', ['settings.activity'], 'Activity log', 'activity'],
    ])
    @php($inAdvanced = collect($advanced)->contains(fn ($link) => request()->routeIs(...$link[1])))
    <div class="settings">
        <nav class="settings-nav" aria-label="Settings">
            @foreach ($everyday as [$route, $patterns, $label, $icon])
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs(...$patterns)])><x-icon :name="$icon"/>{{ $label }}</a>
            @endforeach
            <details class="settings-advanced" @if ($inAdvanced) open @endif>
                <summary>Advanced<x-icon name="chevron-down" class="icon sm"/></summary>
                <div class="settings-advanced-links">
                    @foreach ($advanced as [$route, $patterns, $label, $icon])
                        <a href="{{ route($route) }}" @class(['active' => request()->routeIs(...$patterns)])><x-icon :name="$icon"/>{{ $label }}</a>
                    @endforeach
                </div>
            </details>
        </nav>
        <div class="settings-body">
            @hasSection('guide')<x-page-guide :page="trim(View::yieldContent('guide'))"/>@endif
            @yield('settings')
        </div>
    </div>
@endsection
