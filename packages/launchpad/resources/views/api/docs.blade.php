<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __(':site API', ['site' => \Launchpad\Site\Site::name()]) }}</title>
    <style>body { margin: 0; }</style>
</head>
<body>
    <redoc spec-url="{{ $specUrl }}"></redoc>
    <noscript><p>{{ __('This page needs JavaScript. The description itself is at :url.', ['url' => $specUrl]) }}</p></noscript>
    <script src="https://cdn.jsdelivr.net/npm/redoc@2.1.5/bundles/redoc.standalone.js"></script>
</body>
</html>
