@extends('layouts.simple')
@section('title', 'You don't have access')

@section('content')
    <div class="error-code mt">403</div>
    <h1>You don't have access</h1>
    <p class="muted">This page belongs to someone else, or your role doesn't include it.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
