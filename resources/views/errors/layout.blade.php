<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ config('app.name') }}</title>
    {{-- Inline on purpose: an error page has to render when the build is missing or the app is half down. --}}
    <style>
        :root { --bg: #f8fafc; --card: #fff; --text: #0f172a; --muted: #475569; --line: #e2e8f0; --brand: #2563eb; }
        @media (prefers-color-scheme: dark) { :root { --bg: #020617; --card: #0f172a; --text: #f1f5f9; --muted: #94a3b8; --line: #1e293b; --brand: #3b82f6; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { width: 100%; max-width: 30rem; padding: 32px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; }
        .code { margin: 0; font-size: .8rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 4px 0 8px; font-size: 1.5rem; }
        p { margin: 0 0 20px; color: var(--muted); }
        a.button { display: inline-block; padding: 10px 16px; margin-right: 8px; border-radius: 6px; border: 1px solid var(--line); color: var(--text); text-decoration: none; font-weight: 600; }
        a.button.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        a:focus-visible { outline: 3px solid var(--brand); outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <p class="code">Error @yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <a class="button primary" href="{{ url('/') }}">Go to the home page</a>
    <a class="button" href="javascript:history.back()">Go back</a>
</main>
</body>
</html>
