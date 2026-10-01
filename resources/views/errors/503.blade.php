@extends('layouts.simple')
@section('title', 'Back in a moment')

@section('content')
    <div class="error-code mt">503</div>
    <h1>Back in a moment</h1>
    <p class="muted">We're doing a quick update. Please check back in a few minutes.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
