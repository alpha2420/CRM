{{-- Settings → Automation: everything the CRM does by itself, in one place. --}}
<nav class="tabs automation-tabs" aria-label="Automation">
    <a href="{{ route('settings.autopilot.edit') }}" @class(['active' => request()->routeIs('settings.autopilot.*')])><x-icon name="autopilot" class="icon sm"/>Autopilot</a>
    <a href="{{ route('settings.routing.index') }}" @class(['active' => request()->routeIs('settings.routing.*')])><x-icon name="route" class="icon sm"/>Lead routing</a>
    <a href="{{ route('settings.automations.index') }}" @class(['active' => request()->routeIs('settings.automations.*')])><x-icon name="zap" class="icon sm"/>Automations</a>
    <a href="{{ route('settings.sequences.index') }}" @class(['active' => request()->routeIs('settings.sequences.*')])><x-icon name="send" class="icon sm"/>Sequences</a>
</nav>
