@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>Workspace</h2><p>Your company's name appears in the app and on your lead form.</p></div>
    </div>
    <form method="post" action="{{ route('settings.organization.update') }}" class="card">
        @csrf @method('put')
        <div class="row" style="margin-bottom:18px"><x-avatar :name="$organization->name" size="lg" square/><div><strong>{{ $organization->name }}</strong><div class="muted small">Created {{ $organization->created_at->format('d M Y') }} · {{ $organization->plan()->name }} plan</div></div></div>
        <label class="narrow">Company name <input name="name" value="{{ old('name', $organization->name) }}" required maxlength="100"></label>
        <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
    </form>
@endsection
