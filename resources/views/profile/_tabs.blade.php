<nav class="tabs" style="max-width: 720px">
    <a href="{{ route('profile.edit') }}" @class(['active' => request()->routeIs('profile.edit')])><x-icon name="user" class="icon sm"/>Profile</a>
    <a href="{{ route('security.show') }}" @class(['active' => request()->routeIs('security.show')])><x-icon name="shield" class="icon sm"/>Security</a>
</nav>
