@extends('layouts.app')
@section('title', 'Help')
@section('subtitle', 'Short guides to everything in '.config('app.name').'.')

@section('content')
    @php($topics = __('help.topics'))

    <div class="help-grid">
        <nav class="settings-nav hide-sm">
            @foreach ($topics as [$id, $title, $icon])
                <a href="#{{ $id }}"><x-icon :name="$icon"/>{{ $title }}</a>
            @endforeach
        </nav>
        <div>
            <div class="filters"><div class="search-field"><x-icon name="search"/><input type="search" id="help-search" placeholder="Search help, e.g. WhatsApp template" aria-label="Search help"></div></div>
            @foreach ($topics as [$id, $title, $icon, $questions])
                <section class="card help-topic" id="{{ $id }}">
                    <div class="card-head"><div class="row"><span class="kpi-icon"><x-icon :name="$icon"/></span><h2>{{ $title }}</h2></div></div>
                    @foreach ($questions as [$question, $answer])
                        <details class="help-q">
                            <summary>{{ $question }}</summary>
                            <p>{{ strtr($answer, [':days' => config('crm.dormant_after_days')]) }}</p>
                        </details>
                    @endforeach
                </section>
            @endforeach
            <section class="card row-between">
                <div><strong>Still stuck?</strong><div class="muted small">We usually reply within one working day.</div></div>
                @if (config('crm.support_email'))
                    <a href="mailto:{{ config('crm.support_email') }}" class="btn primary"><x-icon name="mail"/>Email support</a>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
    document.getElementById('help-search').addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        document.querySelectorAll('.help-topic').forEach((topic) => {
            let visible = 0;
            topic.querySelectorAll('.help-q').forEach((q) => {
                const match = !term || q.textContent.toLowerCase().includes(term);
                q.hidden = !match;
                q.open = !!term && match;
                visible += match ? 1 : 0;
            });
            topic.hidden = visible === 0;
        });
    });
</script>
@endpush
