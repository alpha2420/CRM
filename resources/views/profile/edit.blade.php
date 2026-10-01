@extends('layouts.app')
@section('title', 'Your profile')
@section('subtitle', 'Your name, login email and password.')

@section('content')
    <form method="post" action="{{ route('profile.update') }}" class="card" style="max-width: 720px">
        @csrf @method('put')
        <div class="form-section">
            <div class="row" style="margin-bottom:16px"><x-avatar :name="$user->name" size="lg"/><div><h2>{{ $user->name }}</h2><span class="muted">{{ $user->role->label() }} · {{ $user->organization->name }}</span></div></div>
            <div class="form-grid">
                <label>Name <input name="name" value="{{ old('name', $user->name) }}" required maxlength="100"></label>
                <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="150"></label>
            </div>
        </div>
        <div class="form-section">
            <h2>Change password</h2>
            <span class="hint">Leave blank to keep your current password.</span>
            <div class="form-grid">
                <label>Current password <input type="password" name="current_password" autocomplete="current-password"></label>
                <label>New password <input type="password" name="password" minlength="8" autocomplete="new-password"></label>
                <label>Confirm new password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
            </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn primary">Save changes</button></div>
    </form>
@endsection
