<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @isset($seo)
            {{-- A club's public page: what search engines and link previews read (see App\Support\SiteSeo). --}}
            <title>{{ $seo['title'] }}</title>
            @if ($seo['description'] !== '')
                <meta name="description" content="{{ $seo['description'] }}">
            @endif
            @if ($seo['robots'])
                <meta name="robots" content="{{ $seo['robots'] }}">
            @else
                <link rel="canonical" href="{{ $seo['canonical'] }}">
            @endif
            @if ($seo['icon'])
                <link rel="icon" href="{{ $seo['icon'] }}">
                <link rel="apple-touch-icon" href="{{ $seo['icon'] }}">
            @endif
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ $seo['site_name'] }}">
            <meta property="og:title" content="{{ $seo['title'] }}">
            @if ($seo['description'] !== '')
                <meta property="og:description" content="{{ $seo['description'] }}">
            @endif
            <meta property="og:url" content="{{ $seo['canonical'] }}">
            @if ($seo['image'])
                <meta property="og:image" content="{{ $seo['image'] }}">
            @endif
            <meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
            <meta name="twitter:title" content="{{ $seo['title'] }}">
            @if ($seo['description'] !== '')
                <meta name="twitter:description" content="{{ $seo['description'] }}">
            @endif
            @if ($seo['image'])
                <meta name="twitter:image" content="{{ $seo['image'] }}">
            @endif
            @if ($seo['json_ld'] !== '')
                <script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
            @endif
        @else
            <title>{{ config('app.name', 'Laravel') }}</title>
        @endisset
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('superadmin-theme');
                    var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
