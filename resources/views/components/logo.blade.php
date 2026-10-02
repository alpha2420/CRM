@props(['href' => '/'])
{{-- The Convera logo: the mark (a C-shaped chat bubble with the customer dot) and the name.
     Files for print and other uses are in public/images/brand. --}}
@php($gradient = 'cv-'.substr(md5(uniqid('', true)), 0, 8))
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'logo']) }}>
    <svg class="logo-mark" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <defs><linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#5b52f0"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>
        <rect width="64" height="64" rx="15" fill="url(#{{ $gradient }})"/>
        <g style="color:#fff"><path d="M41.64 20.45A15.0 15.0 0 1 0 41.64 42.75" fill="none" stroke="currentColor" stroke-width="8.6" stroke-linecap="round"/><path d="M27.17 49.36L12.00 52.20L14.40 37.86Z" fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><circle cx="46.60" cy="31.6" r="4.5" fill="currentColor"/></g>
    </svg>
    <span class="logo-word">{{ config('app.name') }}</span>
</a>
