<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1756ba">
    <title>@yield('title', 'Utilynk Lists')</title>
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @yield('head')
    @vite('resources/css/app.css')
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
    <div class="wrap header-row">
        <a class="brand" href="/"><span class="brand-icon" aria-hidden="true">✓</span> Utilynk<span class="brand-accent"> Lists</span></a>
        <nav aria-label="Main navigation">
            @unless(isset($compact))<a class="nav-features" href="/features">Features</a>@endunless
            <a href="/lists">My lists</a>
            <a class="button primary header-cta" href="/create">Create list <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>
@yield('content')
@yield('scripts')
</body>
</html>
