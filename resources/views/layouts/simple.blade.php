<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
<body class="guest-simple">
<main class="simple-card">
    <a href="{{ url('/') }}" class="logo"><img src="{{ asset('icons/icon-192.png') }}" alt="">{{ config('app.name') }}</a>
    @yield('content')
</main>
</body>
</html>
