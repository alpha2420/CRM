@extends('layouts.settings')

@section('settings')
    <div class="settings-head"><div><a href="{{ route('users.index') }}" class="back"><x-icon name="arrow-left" class="icon sm"/>Team</a><h2>Add a team member</h2><p>They can log in straight away with the email and password you set.</p></div></div>
    <form method="post" action="{{ route('users.store') }}" class="card">
        @include('users._form')
        <div class="form-actions"><button type="submit" class="btn primary">Add member</button><a href="{{ route('users.index') }}" class="btn ghost">Cancel</a></div>
    </form>
@endsection
