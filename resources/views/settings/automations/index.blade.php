@extends('layouts.settings')
@section('guide', 'automations')

@section('settings')
    <div class="settings-head">
        <div><h2>Automations</h2><p>Let the CRM do the routine work: greet new leads on WhatsApp, route leads by source, schedule follow-ups.</p></div>
        <a href="{{ route('settings.automations.create') }}" class="btn primary"><x-icon name="plus"/>New automation</a>
    </div>

    @forelse ($automations as $automation)
        <section @class(['card', 'automation', 'paused' => ! $automation->is_active])>
            <div class="automation-main">
                <span class="kpi-icon violet"><x-icon name="zap"/></span>
                <div>
                    <strong><span class="toggle-dot"></span>{{ $automation->name }}</strong>
                    <p class="automation-sentence">@include('settings.automations._sentence')</p>
                    <span class="faint small">{{ $automation->is_active ? 'On' : 'Paused' }} · ran {{ number_format($automation->runs) }} {{ Str::plural('time', $automation->runs) }}@if ($automation->last_run_at), last {{ $automation->last_run_at->diffForHumans() }}@endif</span>
                </div>
            </div>
            <div class="row-actions">
                <form method="post" action="{{ route('settings.automations.toggle', $automation) }}">
                    @csrf
                    <button type="submit" class="btn small">{{ $automation->is_active ? 'Pause' : 'Turn on' }}</button>
                </form>
                <a href="{{ route('settings.automations.edit', $automation) }}" class="btn small">Edit</a>
            </div>
        </section>
    @empty
        <section class="card">
            <x-empty icon="zap" title="No automations yet" text="A popular first one: when a new lead arrives, send the WhatsApp welcome template and schedule a follow-up in 2 hours.">
                <a href="{{ route('settings.automations.create') }}" class="btn primary"><x-icon name="plus"/>Create an automation</a>
            </x-empty>
        </section>
    @endforelse
@endsection
