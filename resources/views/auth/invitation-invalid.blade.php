@extends('layouts.guest')
@section('title', 'This invitation has expired')
@section('lead-in', 'The link was already used, revoked, or is more than '.\App\Models\Invitation::VALID_DAYS.' days old. Ask your admin to send a new one.')

@section('content')
    <a href="{{ route('login') }}" class="btn large block">Go to log in</a>
@endsection
