{{-- Inbox: one-to-one chats and, for admins, broadcasts to many leads at once. --}}
@can('admin')
    <nav class="tabs" aria-label="Inbox">
        <a href="{{ route('inbox') }}" @class(['active' => request()->routeIs('inbox')])><x-icon name="inbox" class="icon sm"/>Chats</a>
        <a href="{{ route('broadcasts.index') }}" @class(['active' => request()->routeIs('broadcasts.*')])><x-icon name="megaphone" class="icon sm"/>Broadcasts</a>
    </nav>
@endcan
