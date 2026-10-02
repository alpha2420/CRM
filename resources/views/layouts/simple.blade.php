<!doctype html>
<html lang="en">
<head>
    @include('partials.head')
    <title>@yield('title') · {{ config('app.name') }}</title>
</head>
<body class="guest-simple">
<main class="simple-card">
    <x-logo :href="url('/')"/>
    @yield('content')
</main>
</body>
</html>
