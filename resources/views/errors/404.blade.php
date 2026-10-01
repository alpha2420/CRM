@extends('layouts.simple')
@section('title', 'Page not found')

@section('content')
    <div class="error-code mt">404</div>
    <h1>Page not found</h1>
    <p class="muted">The page you're looking for doesn't exist or has moved.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
