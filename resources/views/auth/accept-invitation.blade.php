@extends('layouts.guest')
@section('title', 'Join '.$invitation->organization->name)
@section('lead-in', ($invitation->inviter?->name ?? 'Your team').' invited you as '.$invitation->role->label().'. Choose your name and password to get started.')

@section('content')
    <form method="post" action="{{ route('invitations.accept', $token) }}" class="stack">
        @csrf
        <label>Email <input type="email" value="{{ $invitation->email }}" disabled></label>
        <label>Your name <input name="name" value="{{ old('name') }}" required maxlength="100" autofocus autocomplete="name"></label>
        <div class="form-grid">
            <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label>Confirm <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
        </div>
        <button type="submit" class="btn primary large block">Join {{ $invitation->organization->name }}</button>
    </form>
@endsection
