@extends('layouts.settings')
@section('guide', 'activity')

@section('settings')
    @php($icons = ['lead' => 'leads', 'user' => 'users', 'auth' => 'logout', 'security' => 'shield', 'workspace' => 'building', 'stage' => 'layers', 'source' => 'tag', 'field' => 'sliders', 'automation' => 'zap', 'integration' => 'plug', 'data' => 'download'])
    <div class="settings-head">
        <div><h2>Activity log</h2><p>Who did what, and when. Kept for 12 months.</p></div>
    </div>

    <form method="get" class="filters">
        <div class="search-field"><x-icon name="search"/><input type="search" name="q" value="{{ request('q') }}" placeholder="Search activity"></div>
        <select name="area" aria-label="Area">
            <option value="">Everything</option>
            @foreach ($areas as $key => $label)
                <option value="{{ $key }}" @selected($area === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="user" aria-label="Person">
            <option value="">Anyone</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(request('user') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Apply</button>
    </form>

    <section class="card flush">
        @if ($entries->isEmpty())
            <x-empty icon="note" title="Nothing here yet" text="Changes to leads, the team and settings will be listed here."/>
        @else
            <div class="scroll-x">
                <table>
                    <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td style="width:44px"><span class="n-icon" style="width:30px;height:30px"><x-icon :name="$icons[$entry->area()] ?? 'note'" class="icon sm"/></span></td>
                            <td>
                                <div>{{ $entry->description }}</div>
                                <div class="muted small">{{ $entry->user?->name ?? 'Automation' }}@if ($entry->ip_address) · {{ $entry->ip_address }}@endif</div>
                            </td>
                            <td class="num nowrap muted small" title="{{ $entry->created_at->local()->format('d M Y, H:i:s') }}">{{ $entry->created_at->local()->format('d M, H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    {{ $entries->links() }}
@endsection
