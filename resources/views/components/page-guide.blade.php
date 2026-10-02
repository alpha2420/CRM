@props(['page'])
{{-- "How this page works": what the page is for, why it helps and how to use it (lang/en/guide.php). Open until hidden; remembered per page. --}}
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
@endif
