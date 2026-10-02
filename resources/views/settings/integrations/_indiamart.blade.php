@if ($integration)
    @php($checked = isset($settings['last_checked_at']) ? \Illuminate\Support\Carbon::parse($settings['last_checked_at']) : null)
    <section class="card row-between" style="flex-wrap:wrap">
        <div class="row">
            <span class="kpi-icon {{ ! empty($settings['last_error']) ? 'hot' : ($checked ? 'ok' : '') }}"><x-icon :name="! empty($settings['last_error']) ? 'x' : ($checked ? 'check-circle' : 'clock')"/></span>
            <div>
                @if (! empty($settings['last_error']))
                    <strong>IndiaMART said: {{ $settings['last_error'] }}</strong>
                    <div class="muted small">Check the CRM key below. We try again every 5 minutes.</div>
                @elseif ($checked)
                    <strong>Working: checked {{ $checked->diffForHumans() }}</strong>
                    <div class="muted small">{{ $settings['last_added'] ?? 0 }} new {{ Str::plural('enquiry', $settings['last_added'] ?? 0) }} last time. New enquiries arrive every 5 minutes.</div>
                @else
                    <strong>Waiting for the first check</strong>
                    <div class="muted small">It runs within 5 minutes, or check now.</div>
                @endif
            </div>
        </div>
        <form method="post" action="{{ route('settings.integrations.test', $type) }}">
            @csrf
            <button type="submit" class="btn">Check now</button>
        </form>
    </section>
@endif

<div class="grid-2">
    <section class="card">
        <form method="post" action="{{ route('settings.integrations.update', $type) }}" class="stack">
            @csrf @method('put')
            <label>IndiaMART CRM key <input type="password" name="crm_key" autocomplete="off" maxlength="200" placeholder="{{ $integration ? 'Saved — leave blank to keep' : 'Paste your IndiaMART CRM key' }}" @required(! $integration)></label>
            <div class="form-actions"><button type="submit" class="btn primary">{{ $integration ? 'Save' : 'Connect' }}</button></div>
        </form>
    </section>
    <section class="card">
        <h3 class="section-label" style="margin-top:0">How to connect</h3>
        <ol class="steps">
            <li>Sign in at <strong>seller.indiamart.com</strong> and open <strong>Settings</strong> → <strong>Account Settings</strong> → <strong>CRM Key</strong>.</li>
            <li>Generate the key there (IndiaMART sends it to your registered email) and copy it.</li>
            <li>Paste the key here and click <strong>Connect</strong>.</li>
            <li>Enquiries from the last 24 hours come in first; after that, every new enquiry arrives within 5 minutes, is assigned to an agent and shows the product they asked about.</li>
        </ol>
    </section>
</div>
