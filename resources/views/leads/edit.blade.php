@extends('layouts.app')
@section('title', 'Edit lead')
@section('subtitle', $lead->name)

@section('content')
    <form method="post" action="{{ route('leads.update', $lead) }}" class="card" style="max-width: 900px">
        @method('put')
        @include('leads._form')
        <div class="form-actions">
            <button type="submit" class="btn primary">Save changes</button>
            <a href="{{ route('leads.show', $lead) }}" class="btn ghost">Cancel</a>
        </div>
    </form>
@endsection
