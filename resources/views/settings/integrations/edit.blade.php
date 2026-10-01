@extends('layouts.settings')

@section('settings')
    @php($settings = $integration?->settings ?? [])
    @php($locked = $type->feature() && ! $organization->canUse($type->feature()))
    <div class="settings-head">
        <div>
            <a href="{{ route('settings.integrations.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Integrations</a>
            <h2>{{ $type->label() }}</h2>
            <p>@if ($integration)<span class="pill ok">Connected</span>@else Not set up yet @endif</p>
        </div>
        @if ($integration)
            <form method="post" action="{{ route('settings.integrations.destroy', $type) }}" data-confirm="Disconnect {{ $type->label() }}?">
                @csrf @method('delete')
                <button type="submit" class="btn danger small">Disconnect</button>
            </form>
        @endif
    </div>

    @if ($locked)
        <div class="alert warning">{{ $type->feature()->label() }} is not included in your plan. <a href="{{ route('settings.billing') }}">See plans</a></div>
    @endif

    @if ($integration && in_array($type, [\App\Enums\IntegrationType::WhatsApp, \App\Enums\IntegrationType::Facebook], true))
        <section class="card row-between" style="flex-wrap:wrap">
            <div class="row">
                <span class="kpi-icon {{ isset($settings['verified_label']) ? 'ok' : '' }}"><x-icon :name="isset($settings['verified_label']) ? 'check-circle' : 'plug'"/></span>
                <div>
                    <strong>{{ $settings['verified_label'] ?? 'Not tested yet' }}</strong>
                    <div class="muted small">{{ isset($settings['verified_at']) ? 'Last checked '.\Illuminate\Support\Carbon::parse($settings['verified_at'])->diffForHumans() : 'Check that the saved credentials work.' }}</div>
                </div>
            </div>
            <form method="post" action="{{ route('settings.integrations.test', $type) }}">
                @csrf
                <button type="submit" class="btn">Test connection</button>
            </form>
        </section>
    @endif

    @include('settings.integrations._'.$type->value)
@endsection
