@props(['page'])
{{-- "How this page works": what the page is for, why it helps and how to use it (lang/en/guide.php).
     Shown in full the first time someone opens the page; after that only a small link, unless they open it again. --}}
@php($guide = __("guide.{$page}"))
@if (is_array($guide))
    @php($bold = fn (string $text) => preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', e($text)))
    <details class="page-guide" data-guide="{{ $page }}" open>
        <summary>
            <span class="page-guide-icon"><x-icon name="help"/></span>
            <span class="page-guide-title"><strong>{{ __('guide.title') }}</strong><span>{{ $guide['what'] }}</span></span>
            <span class="page-guide-toggle" data-open-text="{{ __('guide.hide') }}" data-closed-text="{{ __('guide.show') }}">{{ __('guide.hide') }}</span>
        </summary>
        <div class="page-guide-body">
            <p class="page-guide-why"><b>{{ __('guide.why') }}:</b> {{ $guide['why'] }}</p>
            <ol class="page-guide-steps">
                @foreach ($guide['steps'] as $step)
                    <li>{!! $bold($step) !!}</li>
                @endforeach
            </ol>
            <a href="{{ route('help') }}#{{ $guide['help'] }}" class="page-guide-more">{{ __('guide.more') }}<x-icon name="chevron-right" class="icon sm"/></a>
        </div>
    </details>
    {{-- Runs before the page is drawn, so the box never jumps. app.js remembers later opens and hides. --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (() => {
            const guide = document.currentScript.previousElementSibling;
            try {
                const key = 'crm-guide:' + guide.dataset.guide;
                const state = localStorage.getItem(key);
                if (state === null) localStorage.setItem(key, 'seen');
                else if (state !== 'open') guide.open = false;
            } catch (e) { /* storage blocked: leave it open */ }
        })();
    </script>
@endif
