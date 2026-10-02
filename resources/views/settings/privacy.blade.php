@extends('layouts.settings')

@section('settings')
    <div class="settings-head">
        <div>
            <h2>Privacy & consent</h2>
            <p>India's data protection law (the DPDP Act) asks every business to collect contact details with consent, stop when asked, show or delete a person's data on request, and not keep it longer than needed. Most of this already happens on its own here; this page shows how.</p>
        </div>
    </div>

    <div class="card-stack">
        <section class="card">
            <h3 class="section-label">How the CRM handles each rule</h3>
            <ul class="duty-list">
                <li><x-icon name="check-circle"/><div><strong>Consent is recorded</strong><span>When a lead fills your form, writes on WhatsApp or answers an ad, the lead page records when and how. Your web form tells people how you will contact them.</span></div></li>
                <li><x-icon name="check-circle"/><div><strong>"Stop" is respected</strong><span>A lead who replies STOP gets a confirmation and nothing automatic contacts them again: no sequences, reminders, win-back or templates. You can also stop messages from the lead page.</span></div></li>
                <li><x-icon name="check-circle"/><div><strong>People can see their data</strong><span>On a lead, open <b>⋯ → Download their data</b> to get everything you hold about them as a file you can send them.</span></div></li>
                <li><x-icon name="check-circle"/><div><strong>People can have it deleted</strong><span>On a lead, <b>⋯ → Erase personal data</b> removes their name, phone, notes and messages. The lead stays in reports without its details.</span></div></li>
                <li><x-icon name="check-circle"/><div><strong>Data is kept safe</strong><span>Files are private, every change is in the <a href="{{ route('settings.activity') }}">activity log</a>, and you can require two-step login for your team.</span></div></li>
            </ul>
        </section>

        <section class="card">
            <div class="card-head">
                <div><h3 class="card-title">Opt-out replies</h3><p class="muted small">A WhatsApp reply that is only one of these words changes whether the lead gets messages.</p></div>
                <span class="pill {{ $optedOut ? 'warn' : '' }} nowrap">{{ number_format($optedOut) }} {{ Str::plural('lead', $optedOut) }} opted out</span>
            </div>
            <div class="word-row"><span class="muted small">Stop</span>@foreach ($stopWords as $word)<code>{{ $word }}</code>@endforeach</div>
            <div class="word-row"><span class="muted small">Start again</span>@foreach ($startWords as $word)<code>{{ $word }}</code>@endforeach</div>
        </section>

        <section class="card">
            <h3 class="card-title">How long to keep closed leads</h3>
            <p class="muted small" style="margin:4px 0 14px">Once a lead has been won or lost for this long, its personal data is erased automatically, every night. Counts and values stay in your reports.@if ($erased) {{ number_format($erased) }} {{ Str::plural('lead', $erased) }} erased so far.@endif</p>
            <form method="post" action="{{ route('settings.privacy.update') }}" class="inline-form">
                @csrf @method('put')
                <select name="retention_months" aria-label="Keep closed leads for">
                    <option value="">Keep them (don't erase)</option>
                    @foreach ($choices as $months)
                        <option value="{{ $months }}" @selected($organization->retention_months === $months)>Erase {{ $months >= 12 && $months % 12 === 0 ? ($months / 12).' '.Str::plural('year', $months / 12) : $months.' months' }} after closing</option>
                    @endforeach
                </select>
                <button type="submit" class="btn primary">Save</button>
            </form>
        </section>

        <section class="card">
            <h3 class="card-title">Your whole workspace</h3>
            <p class="muted small" style="margin:4px 0 0">Export everything as a ZIP of spreadsheets, or delete the workspace, under <a href="{{ route('settings.organization.edit') }}">General</a>. Our draft <a href="{{ route('legal', 'privacy') }}" target="_blank" rel="noopener">privacy policy</a> describes what the service does with your data.</p>
        </section>
    </div>
@endsection
