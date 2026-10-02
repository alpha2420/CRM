@extends('layouts.settings')
@section('guide', 'routing')

@section('settings')
    <div class="settings-head">
        <div><h2>Lead routing</h2><p>Decide who gets new leads. Rules are checked from the top; a lead that matches none goes to the next available agent in turn.</p></div>
        <a href="{{ route('settings.routing.create') }}" class="btn primary"><x-icon name="plus"/>New rule</a>
    </div>

    <section class="card flush">
        <div class="card-head"><div><h2>Routing rules</h2><p class="muted small">Leads someone adds by hand keep the owner they choose.</p></div></div>
        @forelse ($rules as $i => $rule)
            <div @class(['route-row', 'paused' => ! $rule->is_active])>
                <span class="route-order">{{ $i + 1 }}</span>
                <div class="grow">
                    <strong>{{ $rule->name }}</strong>@unless ($rule->is_active)<span class="pill" style="margin-left:8px">Paused</span>@endunless
                    <div class="muted small">@include('settings.routing._rule')</div>
                </div>
                <div class="row-actions">
                    <form method="post" action="{{ route('settings.routing.move', [$rule, 'up']) }}">@csrf<button type="submit" class="btn small square" aria-label="Move {{ $rule->name }} up" @disabled($loop->first)><x-icon name="arrow-left" class="icon sm rot-up"/></button></form>
                    <form method="post" action="{{ route('settings.routing.move', [$rule, 'down']) }}">@csrf<button type="submit" class="btn small square" aria-label="Move {{ $rule->name }} down" @disabled($loop->last)><x-icon name="arrow-left" class="icon sm rot-down"/></button></form>
                    <form method="post" action="{{ route('settings.routing.toggle', $rule) }}">@csrf<button type="submit" class="btn small">{{ $rule->is_active ? 'Pause' : 'Turn on' }}</button></form>
                    <a href="{{ route('settings.routing.edit', $rule) }}" class="btn small">Edit</a>
                </div>
            </div>
        @empty
            <x-empty icon="route" title="No routing rules" text="Every new lead goes to the next available agent in turn. Add a rule to send, for example, Facebook leads from Pune to your Pune team."/>
        @endforelse
    </section>

    <section class="card flush">
        <div class="card-head">
            <div><h2>Who takes new leads</h2><p class="muted small">People marked away are skipped until they're back. They can set this themselves from their name at the bottom left.</p></div>
        </div>
        <div class="scroll-x"><table>
            <thead><tr><th>Person</th><th class="num">Open leads</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($people as $person)
                @php($full = $organization->max_open_leads !== null && $person->open_count >= $organization->max_open_leads)
                <tr>
                    <td><span class="person"><x-avatar :name="$person->name" size="sm"/>{{ $person->name }}</span> <span class="muted small">{{ $person->role->label() }}</span></td>
                    <td class="num">{{ $person->open_count }}</td>
                    <td>
                        @if (! $person->is_available)<span class="pill warn">Away</span>
                        @elseif ($full)<span class="pill">At limit</span>
                        @elseif ($person->isAdmin())<span class="pill info" title="Admins only get leads through rules that name them">Rules only</span>
                        @else<span class="pill ok">Available</span>@endif
                    </td>
                    <td class="row-actions">
                        <form method="post" action="{{ route('settings.routing.availability', $person) }}">@csrf<button type="submit" class="btn small">{{ $person->is_available ? 'Mark away' : 'Mark available' }}</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <form method="post" action="{{ route('settings.routing.limit') }}" class="card-foot inline-form">
            @csrf @method('put')
            <label class="inline" for="max-open">Give no one more than</label>
            <input type="number" id="max-open" name="max_open_leads" value="{{ old('max_open_leads', $organization->max_open_leads) }}" min="1" max="5000" placeholder="any" class="inline-num">
            <span class="muted">open leads at a time</span>
            <button type="submit" class="btn small">Save</button>
        </form>
    </section>
    <p class="hint">If everyone is away or at the limit, leads still go to someone, so none are left without an owner.</p>
@endsection
