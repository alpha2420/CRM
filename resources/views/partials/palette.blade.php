{{-- Command palette (Ctrl/⌘ K) and the keyboard shortcut list (?). Works through public/js/app.js. --}}
@php($commands = array_filter([
    ['Go to', route('dashboard'), 'dashboard', 'Dashboard', 'home overview', 'G D'],
    ['Go to', route('today'), 'check-circle', 'My day', 'today tasks to-dos follow-ups agenda', 'G M'],
    ['Go to', route('leads.index'), 'leads', 'Leads', 'list contacts customers', 'G L'],
    ['Go to', route('leads.index', ['view' => 'board']), 'board', 'Pipeline board', 'kanban stages drag', null],
    ['Go to', route('leads.index', ['stage' => 'due']), 'clock', 'Follow-ups due (everyone)', 'overdue calls', null],
    $nav['inbox'] ? ['Go to', route('inbox'), 'inbox', 'Inbox', 'whatsapp chats messages', 'G I'] : null,
    auth()->user()->can('admin') ? ['Go to', route('reports'), 'reports', 'Reports', 'analytics funnel campaigns speed', 'G R'] : null,
    auth()->user()->can('admin') ? ['Go to', route('settings.organization.edit'), 'settings', 'Settings', 'workspace configuration', 'G S'] : null,
    auth()->user()->can('admin') ? ['Go to', route('settings.autopilot.edit'), 'autopilot', 'Autopilot', 'automatic working hours away', null] : null,
    auth()->user()->can('admin') ? ['Go to', route('users.index'), 'users', 'Team', 'agents invite people', null] : null,
    ['Go to', route('help'), 'help', 'Help', 'guide how faq', null],
    ['Do', route('leads.create'), 'plus', 'Add a new lead', 'create new lead', 'N'],
    ['Do', route('today').'#add', 'check', 'Add a to-do', 'task reminder', 'T'],
    auth()->user()->can('admin') ? ['Do', route('leads.import'), 'upload', 'Import leads from a spreadsheet', 'csv excel upload', null] : null,
    ['Do', '#theme', 'monitor', 'Switch light / dark mode', 'theme dark light night', null],
    ['Do', '#shortcuts', 'help', 'Show keyboard shortcuts', 'keys hotkeys', '?'],
]))
<dialog class="palette" id="palette" aria-label="Search and commands" data-search-url="{{ route('search') }}">
    <div class="palette-input">
        <x-icon name="search"/>
        <input type="text" placeholder="Search leads, or type what you want to do…" autocomplete="off" spellcheck="false" aria-label="Search leads or commands" aria-controls="palette-results">
        <kbd>Esc</kbd>
    </div>
    <div class="palette-results" id="palette-results" role="listbox">
        <div class="palette-group" data-leads hidden><div class="palette-label">Leads</div><ul></ul></div>
        @foreach (collect($commands)->groupBy(0) as $group => $items)
            <div class="palette-group"><div class="palette-label">{{ $group }}</div>
                <ul>
                    @foreach ($items as [, $url, $icon, $label, $keywords, $keys])
                        <li><a href="{{ $url }}" data-keywords="{{ strtolower($label.' '.$keywords) }}" role="option"><x-icon :name="$icon"/><span class="grow">{{ $label }}</span>@if ($keys)<kbd>{{ $keys }}</kbd>@endif</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p class="palette-empty" hidden>Nothing found. Try a name, phone number or company.</p>
    </div>
    <div class="palette-foot"><span><kbd>↑</kbd><kbd>↓</kbd> move</span><span><kbd>Enter</kbd> open</span><span><kbd>?</kbd> all shortcuts</span></div>
</dialog>

<dialog class="palette shortcuts" id="shortcuts" aria-label="Keyboard shortcuts">
    <div class="shortcuts-head"><h2>Keyboard shortcuts</h2><button type="button" class="icon-btn" data-close aria-label="Close"><x-icon name="x"/></button></div>
    <dl class="shortcut-list">
        <div><dt><kbd data-mod>Ctrl</kbd><kbd>K</kbd></dt><dd>Search leads and jump anywhere</dd></div>
        <div><dt><kbd>/</kbd></dt><dd>Search leads in the top bar</dd></div>
        <div><dt><kbd>N</kbd></dt><dd>Add a new lead</dd></div>
        <div><dt><kbd>T</kbd></dt><dd>Add a to-do</dd></div>
        <div><dt><kbd>G</kbd> then <kbd>D</kbd></dt><dd>Dashboard</dd></div>
        <div><dt><kbd>G</kbd> then <kbd>M</kbd></dt><dd>My day</dd></div>
        <div><dt><kbd>G</kbd> then <kbd>L</kbd></dt><dd>Leads</dd></div>
        @if ($nav['inbox'])<div><dt><kbd>G</kbd> then <kbd>I</kbd></dt><dd>Inbox</dd></div>@endif
        @can('admin')
            <div><dt><kbd>G</kbd> then <kbd>R</kbd></dt><dd>Reports</dd></div>
            <div><dt><kbd>G</kbd> then <kbd>S</kbd></dt><dd>Settings</dd></div>
        @endcan
        <div><dt><kbd>?</kbd></dt><dd>This list</dd></div>
    </dl>
</dialog>
