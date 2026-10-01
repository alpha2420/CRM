@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div><h2>General</h2><p>Your company's name and time zone.</p></div>
    </div>
    <form method="post" action="{{ route('settings.organization.update') }}" class="card">
        @csrf @method('put')
        <div class="row" style="margin-bottom:18px"><x-avatar :name="$organization->name" size="lg" square/><div><strong>{{ $organization->name }}</strong><div class="muted small">Created {{ $organization->created_at->local()->format('d M Y') }} · {{ $organization->plan()->name }} plan</div></div></div>
        <div class="form-grid" style="max-width: 720px">
            <label>Company name <input name="name" value="{{ old('name', $organization->name) }}" required maxlength="100"></label>
            <label>Time zone
                <select name="timezone" required>
                    @foreach (\DateTimeZone::listIdentifiers() as $zone)
                        <option value="{{ $zone }}" @selected(old('timezone', $organization->timezone) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                    @endforeach
                </select>
                <span class="hint">Follow-up times, reminders and reports use this time zone. It is now {{ \App\Support\LocalTime::now()->format('H:i') }} here.</span>
            </label>
        </div>
        <div class="section-label">Security</div>
        <label class="check"><input type="checkbox" name="require_two_factor" value="1" @checked(old('require_two_factor', $organization->require_two_factor))> Require two-factor login for everyone in this workspace</label>
        <p class="hint" style="margin:6px 0 0 24px">Members without it are asked to set it up before they can continue.</p>
        <div class="form-actions"><button type="submit" class="btn primary">Save</button></div>
    </form>

    <section class="card">
        <div class="card-head" style="margin-bottom:10px"><div><h2>Your data</h2><p class="muted small">Download everything this workspace has stored: leads, follow-ups, WhatsApp messages, team, settings and the activity log, as spreadsheet (CSV) files.</p></div></div>
        <form method="post" action="{{ route('settings.data.export') }}" class="inline-form">
            @csrf
            <input type="password" name="password" required placeholder="Your password" autocomplete="current-password" aria-label="Password">
            <button type="submit" class="btn"><x-icon name="download"/>Download all data (.zip)</button>
        </form>
    </section>

    <section class="card" style="border-color:#fecaca">
        <div class="card-head" style="margin-bottom:10px"><div><h2 style="color:var(--danger)">Delete workspace</h2><p class="muted small">Permanently deletes {{ $organization->name }}: every lead, message, user and setting. This can't be undone, so download your data first.</p></div></div>
        <form method="post" action="{{ route('settings.workspace.destroy') }}" class="stack narrow" data-confirm="Delete this workspace and ALL its data permanently?">
            @csrf @method('delete')
            <label><span>Type <strong>{{ $organization->name }}</strong> to confirm</span><input name="confirm_name" required autocomplete="off"></label>
            <label>Your password <input type="password" name="password" required autocomplete="current-password"></label>
            <div><button type="submit" class="btn danger"><x-icon name="trash"/>Delete workspace forever</button></div>
        </form>
    </section>
@endsection
