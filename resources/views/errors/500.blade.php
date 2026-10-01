@extends('layouts.simple')
@section('title', 'Something went wrong')

@section('content')
    <div class="error-code mt">500</div>
    <h1>Something went wrong</h1>
    <p class="muted">An unexpected error happened on our side. Please try again in a moment.</p>
    <a href="{{ url('/') }}" class="btn primary">Go to home</a>
@endsection
