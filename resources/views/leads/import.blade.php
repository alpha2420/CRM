@extends('layouts.settings')
@section('guide', 'import')

@section('settings')
    <div class="settings-head">
        <div><h2>Import / Export</h2><p>Bring leads in from a spreadsheet, or download them all.</p></div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-head"><div class="row"><span class="kpi-icon"><x-icon name="upload"/></span><div><h2>Import from CSV</h2><span class="muted small">Excel: File → Save as → CSV</span></div></div></div>
            <form method="post" action="{{ route('leads.import') }}" enctype="multipart/form-data" class="stack">
                @csrf
                <div class="dropzone">
                    <input type="file" name="file" accept=".csv,text/csv" required aria-label="CSV file">
                    <div class="muted small mt">Up to {{ number_format(config('crm.import_max_rows')) }} rows · 5 MB</div>
                </div>
                <button type="submit" class="btn primary">Import leads</button>
            </form>
            <div class="section-label">Columns</div>
            <p class="muted small">Required: <code>name</code>, <code>phone</code>. Optional: <code>email</code>, <code>company</code>, <code>city</code>, <code>source</code>, <code>status</code>, <code>value</code>, <code>priority</code>, <code>notes</code>, plus any custom field by its label. Duplicate phone numbers are skipped; new leads are shared between agents automatically.</p>
            <a href="{{ route('leads.template') }}" class="btn small"><x-icon name="download"/>Download template</a>

            @if (session('import_errors'))
                <div class="section-label">Skipped rows</div>
                <div class="alert warning"><ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
        </section>

        <section class="card">
            <div class="card-head"><div class="row"><span class="kpi-icon ok"><x-icon name="download"/></span><div><h2>Export</h2><span class="muted small">Every lead, with custom fields</span></div></div></div>
            <p class="muted">Download all leads in your workspace as a CSV file that opens in Excel or Google Sheets. Spreadsheet formulas are neutralised for safety.</p>
            <a href="{{ route('leads.export') }}" class="btn"><x-icon name="download"/>Export all leads</a>
        </section>
    </div>
@endsection
