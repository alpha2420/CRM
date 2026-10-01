@extends('layouts.simple')
@section('title', 'Slow down a little')

@section('content')
    <div class="error-code mt">429</div>
    <h1>Slow down a little</h1>
    <p class="muted">Too many attempts in a short time. Please wait a minute and try again.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
