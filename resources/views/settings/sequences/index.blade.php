@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Sequences</h2><p>Timed follow-ups that run by themselves: WhatsApp messages and reminders over days, stopping when the lead replies or closes.</p></div>
        <a href="{{ route('settings.sequences.create') }}" class="btn primary"><x-icon name="plus"/>New sequence</a>
    </div>

    @forelse ($sequences as $sequence)
        <section @class(['card', 'automation', 'paused' => ! $sequence->is_active])>
            <div class="automation-main">
                <span class="kpi-icon"><x-icon name="send"/></span>
                <div class="grow">
                    <strong><span class="toggle-dot"></span>{{ $sequence->name }}</strong>
                    <ol class="step-line">
                        @foreach ($sequence->steps as $step)
                            <li><span class="pill">Day {{ $step->day }}</span>{{ $step->summary() }}</li>
                        @endforeach
                    </ol>
                    <span class="muted small">{{ $sequence->is_active ? 'On' : 'Paused' }} · {{ $sequence->active_enrollments_count }} {{ Str::plural('lead', $sequence->active_enrollments_count) }} in it now{{ $sequence->stop_on_reply ? ' · stops when the lead replies' : '' }}</span>
                </div>
            </div>
            <div class="row-actions">
                <form method="post" action="{{ route('settings.sequences.toggle', $sequence) }}">
                    @csrf
                    <button type="submit" class="btn small">{{ $sequence->is_active ? 'Pause' : 'Turn on' }}</button>
                </form>
                <a href="{{ route('settings.sequences.edit', $sequence) }}" class="btn small">Edit</a>
            </div>
        </section>
    @empty
        <section class="card">
            <x-empty icon="send" title="No sequences yet" text="A popular first one: day 0 the welcome template, day 1 the brochure, day 3 a reminder to call, day 7 an offer.">
                <a href="{{ route('settings.sequences.create') }}" class="btn primary"><x-icon name="plus"/>Create a sequence</a>
            </x-empty>
        </section>
    @endforelse
@endsection
