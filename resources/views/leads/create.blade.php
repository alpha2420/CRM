@extends('layouts.app')
@section('title', 'Add lead')
@section('subtitle', 'New leads are assigned automatically unless you pick an owner.')

@section('content')
    <form method="post" action="{{ route('leads.store') }}" class="card" style="max-width: 900px">
        @include('leads._form')
        <div class="form-actions">
            <button type="submit" class="btn primary">Save lead</button>
            <a href="{{ route('leads.index') }}" class="btn ghost">Cancel</a>
        </div>
    </form>
@endsection
