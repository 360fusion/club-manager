<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(strtolower(strtok(app()->getLocale(), '-_')), (array) config('launchpad.rtl_locales'), true) ? 'rtl' : 'ltr' }}"@if (in_array(auth()->user()?->theme, ['light', 'dark'], true)) data-theme="{{ auth()->user()->theme }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- the same stylesheet and colours as Launchpad's own pages, so a page looks the same whichever draws it --}}
    <link rel="stylesheet" href="{{ \Launchpad\Site\Theme::stylesheet() }}">
    <style>{!! \Launchpad\Site\Theme::css() !!}</style>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
