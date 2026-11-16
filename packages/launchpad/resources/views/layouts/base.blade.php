<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(strtolower(strtok(app()->getLocale(), '-_')), (array) config('launchpad.rtl_locales'), true) ? 'rtl' : 'ltr' }}"@if (in_array(auth()->user()?->theme, ['light', 'dark'], true)) data-theme="{{ auth()->user()->theme }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($siteName = \Launchpad\Site\Site::name())
    @php($pageTitle = trim($__env->yieldContent('title')) ?: $siteName)
    <title>{{ $pageTitle === $siteName ? $siteName : $pageTitle.' | '.$siteName }}</title>
    @if (trim($__env->yieldContent('description')))
        <meta name="description" content="@yield('description')">
        <meta property="og:description" content="@yield('description')">
    @endif
    @if (View::hasSection('noindex'))
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
    @endif
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="stylesheet" href="{{ \Launchpad\Site\Theme::stylesheet() }}">
    <style nonce="{{ $cspNonce ?? '' }}">{!! \Launchpad\Site\Theme::css() !!}</style>
    @stack('head')
</head>
<body>
<a class="lp-skip" href="#main">{{ __('Skip to the content') }}</a>
@if (\Launchpad\Admin\Impersonation::active())
    <div class="lp-impersonating" role="alert">
        <span>{{ \Launchpad\Support\Trans::html("You are signed in as :name (:email), as support. What you do here is the customer's, and is logged.", ['name' => new \Illuminate\Support\HtmlString('<strong>'.e(auth('web')->user()?->name).'</strong>'), 'email' => auth('web')->user()?->email]) }}</span>
        <form method="post" action="{{ route('impersonate.stop') }}">@csrf <button type="submit">{{ __('Stop and go back to HQ') }}</button></form>
    </div>
@endif
<header class="lp-header">
    <a class="lp-brand" href="{{ config('launchpad.routes.home') }}">{{ $siteName }}</a>
    <nav aria-label="{{ __('Main') }}">
        @foreach ((array) config('launchpad.site.nav') as [$label, $href])
            <a href="{{ $href }}">{{ __($label) }}</a>
        @endforeach
    </nav>
    @auth
        <div class="lp-header__actions">
            @if (\Launchpad\Notifications\Bell::enabled(auth()->user()))@include('launchpad::layouts.bell')@endif
            @include('launchpad::layouts.user-menu')
        </div>
    @else
        <nav class="lp-header__actions" aria-label="{{ __('Account') }}">
            <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
            @if (Route::has('register'))<a class="lp-btn" href="{{ route('register') }}">{{ __('Start free') }}</a>@endif
        </nav>
    @endauth
</header>
<main id="main" class="lp-main @yield('main-class')">
    @yield('content')
</main>
<footer class="lp-footer">
    <div class="lp-footer__cols">
        <div class="lp-footer__about">
            <a class="lp-brand" href="{{ config('launchpad.routes.home') }}">{{ $siteName }}</a>
            @if (\Launchpad\Site\Site::get('tagline'))<p class="lp-muted">{{ \Launchpad\Site\Site::get('tagline') }}</p>@endif
        </div>
        @foreach (\Launchpad\Site\Footer::columns() as $heading => $links)
            <nav aria-label="{{ __($heading) }}">
                <h2 class="lp-footer__heading">{{ __($heading) }}</h2>
                <ul>
                    @foreach ($links as [$label, $href])
                        <li><a href="{{ url($href) }}">{{ __($label) }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endforeach
    </div>
    <div class="lp-footer__bar">
        <p class="lp-muted">&copy; {{ date('Y') }} {{ \Launchpad\Site\Site::get('company') ?? $siteName }}</p>
        @if (Route::has('cookie-preferences'))<a href="{{ route('cookie-preferences') }}">{{ __('Cookie preferences') }}</a>@endif
        @if (count(\Launchpad\Support\Locales::supported()) > 1)
            <form class="lp-locale" method="post" action="{{ route('locale.update') }}" aria-label="{{ __('Language') }}">
                @csrf
                @foreach (\Launchpad\Support\Locales::supported() as $code => $name)
                    <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" @if ($code === app()->getLocale()) aria-current="true" @endif>{{ $name }}</button>
                @endforeach
            </form>
        @endif
    </div>
</footer>
@if (Route::has('cookie-consent'))@include('launchpad::site.consent-banner')@endif
@auth
<script nonce="{{ $cspNonce ?? '' }}">
    // the bell and the user menu close when you click elsewhere or press Escape
    const closeMenus = (except) => document.querySelectorAll('.lp-bell[open], .lp-usermenu[open]').forEach((d) => d !== except && d.removeAttribute('open'));
    document.addEventListener('click', (e) => closeMenus(e.target.closest('.lp-bell, .lp-usermenu')));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closeMenus(null));
</script>
@endauth
@stack('scripts')
</body>
</html>
