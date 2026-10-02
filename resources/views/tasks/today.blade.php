@extends('layouts.app')
@section('title', 'My day')
@php($open = $day['overdue']->count() + $day['today']->count())
@section('subtitle', \App\Support\LocalTime::now()->format('l j F').' · '.($open ? $open.' '.Str::plural('thing', $open).' to do today' : 'nothing left for today'))

@section('content')
    <div class="grid-main myday">
        <div class="col-stack">
            <section class="card">
                @include('tasks._add', ['placeholder' => 'Add a to-do, e.g. Send the GST invoice to Kiran'])
            </section>

            @if ($day['overdue']->isNotEmpty())
                <section class="card flush">
                    <div class="card-head"><div><h2 class="tone-hot">Overdue</h2><p class="muted small">These should have happened already: do them first.</p></div><span class="pill bad">{{ $day['overdue']->count() }}</span></div>
                    <ul class="agenda">@each('tasks._item', $day['overdue'], 'item')</ul>
                </section>
            @endif

            <section class="card flush">
                <div class="card-head"><div><h2>Today</h2><p class="muted small">Follow-ups, meetings and to-dos, by time.</p></div><span class="pill info">{{ $day['today']->count() }}</span></div>
                @if ($day['today']->isEmpty())
                    <x-empty icon="check-circle" title="Nothing else planned for today" text="When you log a call and pick the next date, or book a meeting, it shows up here on that day."/>
                @else
                    <ul class="agenda">@each('tasks._item', $day['today'], 'item')</ul>
                @endif
            </section>

            @if ($day['someday']->isNotEmpty())
                <section class="card flush">
                    <div class="card-head"><div><h2>No date</h2><p class="muted small">To-dos without a day. Give them one when you can.</p></div><span class="pill">{{ $day['someday']->count() }}</span></div>
                    <ul class="agenda">@each('tasks._item', $day['someday'], 'item')</ul>
                </section>
            @endif
        </div>

        <aside class="col-stack">
            @php($total = $day['done'] + $open)
            <section class="card">
                <div class="speed-headline">
                    <strong class="tone-ok">{{ $day['done'] }}</strong>
                    <span>done today<span class="muted small">{{ $open ? $open.' to go' : 'All clear: well done!' }}</span></span>
                </div>
                <div class="meter"><span style="width: {{ $total ? round($day['done'] / $total * 100) : 100 }}%; background: var(--success-dot)"></span></div>
            </section>

            @if ($day['waiting']->isNotEmpty())
                <section class="card">
                    <div class="card-head"><div><h2>New leads waiting</h2><p class="muted small">Reply within 5 minutes: they're far more likely to buy.</p></div></div>
                    <ul class="mini-list">
                        @foreach ($day['waiting'] as ['lead' => $waitingLead, 'seconds' => $seconds])
                            <li>
                                <a href="{{ route('leads.show', $waitingLead) }}">
                                    <x-avatar :name="$waitingLead->name"/>
                                    <span class="grow"><strong>{{ $waitingLead->name }}</strong><span>{{ $waitingLead->source?->name ?? 'New lead' }}</span></span>
                                    <span class="end"><b @class(['tone-hot' => $seconds > \App\Speed\ResponseTimes::TARGET_SECONDS])>{{ \App\Support\Duration::seconds($seconds) }}</b></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="card guide">
                <h3 class="card-title">How My day works</h3>
                <ul class="guide-list">
                    <li><x-icon name="phone"/><span><b>Follow-ups</b> appear on the day you picked when you logged a call. Tap <b>Log call</b> after you speak to them.</span></li>
                    <li><x-icon name="calendar"/><span><b>Meetings</b> you booked on a lead appear at their time.</span></li>
                    <li><x-icon name="check-circle"/><span><b>To-dos</b> are anything else: add them above or on a lead, and tick them off when done.</span></li>
                </ul>
            </section>
        </aside>
    </div>
@endsection
