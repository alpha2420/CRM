<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/landing.css') }}">
</head>
<body class="landing">
@include('partials.public-nav')
<main class="l-section">
    <article class="l-container legal">
        <span class="l-eyebrow">Last updated {{ $updated }}</span>
        {!! $html !!}
    </article>
</main>
@include('partials.public-footer')
</body>
</html>
