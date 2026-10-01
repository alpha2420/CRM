@extends('layouts.settings')

@section('settings')
    <div class="settings-head"><div><a href="{{ route('users.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Team</a><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div></div>
    <form method="post" action="{{ route('users.update', $user) }}" class="card">
        @method('put')
        @include('users._form')
        <div class="form-actions"><button type="submit" class="btn primary">Save changes</button><a href="{{ route('users.index') }}" class="btn ghost">Cancel</a></div>
    </form>
@endsection
