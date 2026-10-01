@extends('layouts.form')
@section('title', 'Thank you')

@section('content')
    <div class="thanks">
        <div class="tick-big"><x-icon name="check"/></div>
        <h1>{{ $integration->settings['thank_you'] ?? 'Thanks! We will be in touch shortly.' }}</h1>
        <p class="muted">{{ $integration->organization->name }}</p>
    </div>
@endsection
