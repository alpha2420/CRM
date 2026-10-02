<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
<body>
@php($user = auth()->user())
@php($organization = $user->organization)
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a href="{{ route('dashboard') }}" class="logo"><img src="{{ asset('icons/icon-192.png') }}" alt="">{{ config('app.name') }}</a>
            <button type="button" class="icon-btn hide-desktop" data-toggle-nav aria-label="Close menu"><x-icon name="x"/></button>
        </div>

        <div class="workspace">
            <x-avatar :name="$organization->name" square/>
            <div class="grow">
                <strong title="{{ $organization->name }}">{{ $organization->name }}</strong>
                @if ($organization->onTrial())
                    <a class="plan-pill {{ $organization->trialDaysLeft() <= 3 ? 'warn' : '' }}" href="{{ $user->isAdmin() ? route('settings.billing') : route('dashboard') }}">Trial · {{ $organization->trialDaysLeft() }} {{ Str::plural('day', $organization->trialDaysLeft()) }} left</a>
                @else
                    <span class="plan-pill">{{ $organization->plan()->name }} plan</span>
                @endif
            </div>
        </div>

        <nav class="nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])><x-icon name="dashboard"/>Dashboard</a>
            <a href="{{ route('today') }}" @class(['active' => request()->routeIs('today')])><x-icon name="check-circle"/>My day @if ($nav['dueToday'])<span class="count hot">{{ $nav['dueToday'] > 99 ? '99+' : $nav['dueToday'] }}</span>@endif</a>
            <a href="{{ route('leads.index') }}" @class(['active' => request()->routeIs('leads.index', 'leads.show', 'leads.create', 'leads.edit')])><x-icon name="leads"/>Leads</a>
            @if ($nav['inbox'])
                <a href="{{ route('inbox') }}" @class(['active' => request()->routeIs('inbox')])><x-icon name="inbox"/>Inbox @if ($nav['unreadChats'])<span class="count">{{ $nav['unreadChats'] }}</span>@endif</a>
            @endif
            @can('admin')
                <a href="{{ route('reports') }}" @class(['active' => request()->routeIs('reports')])><x-icon name="reports"/>Reports</a>
            @endcan

            @if ($user->can('admin') || $user->can('platform'))
                <div class="nav-label">Admin</div>
                @can('admin')
                    <a href="{{ route('settings.organization.edit') }}" @class(['active' => request()->routeIs('settings.*', 'users.*', 'leads.import')])><x-icon name="settings"/>Settings</a>
                @endcan
                @can('platform')
                    <a href="{{ route('platform.index') }}" @class(['active' => request()->routeIs('platform.*')])><x-icon name="platform"/>Platform</a>
                @endcan
            @endif
        </nav>

        <div class="sidebar-bottom">
            <nav class="nav" aria-label="Support">
                <a href="{{ route('help') }}" @class(['active' => request()->routeIs('help')])><x-icon name="help"/>Help</a>
            </nav>
            <details class="user-menu">
                <summary>
                    <x-avatar :name="$user->name"/>
                    <span class="who"><strong>{{ $user->name }}</strong><span>{{ $user->role->label() }}@unless ($user->is_available) · <span class="away-tag">Away</span>@endunless</span></span>
                    <x-icon name="chevron-down" class="icon sm faint"/>
                </summary>
                <div class="menu">
                    <form method="post" action="{{ route('availability') }}">
                        @csrf
                        <button type="submit"><x-icon name="{{ $user->is_available ? 'clock' : 'check-circle' }}" class="icon sm"/>{{ $user->is_available ? "I'm away (pause new leads)" : "I'm back (get new leads)" }}</button>
                    </form>
                    <a href="{{ route('profile.edit') }}"><x-icon name="user" class="icon sm"/>Profile</a>
                    <a href="{{ route('security.show') }}"><x-icon name="shield" class="icon sm"/>Security</a>
                    <a href="{{ route('notifications.index') }}"><x-icon name="bell" class="icon sm"/>Notifications</a>
                    @can('admin')<a href="{{ route('settings.billing') }}"><x-icon name="card" class="icon sm"/>Billing</a>@endcan
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="danger"><x-icon name="logout" class="icon sm"/>Log out</button>
                    </form>
                </div>
            </details>
        </div>
    </aside>
    <div class="nav-backdrop"></div>

    <div class="main-wrap">
        <header class="topbar">
            <button type="button" class="icon-btn hide-desktop" data-toggle-nav aria-label="Open menu"><x-icon name="menu"/></button>
            <form action="{{ route('leads.index') }}" method="get" class="search" role="search">
                <x-icon name="search"/>
                <input type="search" name="q" id="global-search" placeholder="Search leads by name, phone or company" value="{{ request()->routeIs('leads.index') ? request('q') : '' }}" aria-label="Search leads">
                <kbd>/</kbd>
            </form>
            <div class="topbar-actions">
                @php($unread = $nav['unreadNotifications'])
                <a href="{{ route('notifications.index') }}" class="bell" aria-label="Notifications{{ $unread ? ", {$unread} unread" : '' }}">
                    <x-icon name="bell"/>
                    @if ($unread)<span class="dot">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
                </a>
                <a href="{{ route('leads.create') }}" class="btn primary new-lead" aria-label="New lead"><x-icon name="plus"/><span>New lead</span></a>
            </div>
        </header>
        <main @class(['main', 'full' => View::hasSection('wide')])>
            @hasSection('header')
                @yield('header')
            @else
                <header class="page-head">
                    <div>
                        <h1>@yield('title')</h1>
                        @hasSection('subtitle')<p class="page-sub">@yield('subtitle')</p>@endif
                    </div>
                    <div class="actions">@yield('actions')</div>
                </header>
            @endif
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
