@extends('launchpad::layouts.base')
@section('title', __('Sign in'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Sign in') }}</h1>
    <x-launchpad::status />
    <form method="post" action="{{ route('login') }}">
        @csrf
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="username" autofocus />
        <x-launchpad::field name="password" :label="__('Password')" type="password" autocomplete="current-password" />
        <div class="lp-check"><input type="checkbox" id="remember" name="remember" value="1"><label for="remember">{{ __('Keep me signed in') }}</label></div>
        <x-launchpad::turnstile :key="$turnstileKey" />
        <button class="lp-btn" type="submit">{{ __('Sign in') }}</button>
    </form>
    <x-launchpad::social />
    @if ($canUseSso ?? false)<p><a href="{{ route('sso.create') }}">{{ __('Sign in with single sign-on') }}</a></p>@endif
    @if ($canUsePasskeys ?? false)
    <div data-passkey hidden>
        <div class="lp-alert lp-alert--error" role="alert" id="lp-passkey-error" hidden></div>
        <p><button class="lp-btn lp-btn--quiet" type="button" id="lp-passkey-sign-in">{{ __('Sign in with a passkey') }}</button></p>
    </div>
    @include('launchpad::partials.passkeys')
    @push('scripts')
    <script nonce="{{ $cspNonce ?? '' }}">
        document.getElementById('lp-passkey-sign-in')?.addEventListener('click', () => {
            const error = document.getElementById('lp-passkey-error');
            window.lpPasskeys.signIn(document.getElementById('remember').checked).then((url) => { window.location = url; })
                .catch((e) => { error.textContent = e.message; error.hidden = false; });
        });
    </script>
    @endpush
    @endif
    @if ($canUseLoginLinks ?? false)<p><a href="{{ route('login.link') }}">{{ __('Email me a sign-in link') }}</a></p>@endif
</div>
<p class="lp-links">
    @if ($canResetPassword)<a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>@endif
    <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
</p>
@endsection
