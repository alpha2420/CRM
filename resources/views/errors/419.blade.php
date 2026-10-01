@extends('layouts.simple')
@section('title', 'Page expired')

@section('content')
    <div class="error-code mt">419</div>
    <h1>Page expired</h1>
    <p class="muted">Your session timed out for security. Go back, refresh, and try again.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
