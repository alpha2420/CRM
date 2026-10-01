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

    @include('settings.integrations._'.$type->value)
@endsection
