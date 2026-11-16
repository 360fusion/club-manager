@extends('launchpad::layouts.base')
@section('title', __('Create your account'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Create your account') }}</h1>
    @if ($waitlistUrl ?? null)<div class="lp-alert" role="note">{{ __('Sign-up is by invitation for now.') }} <a href="{{ $waitlistUrl }}">{{ __('Join the waitlist') }}</a></div>@endif
    <form method="post" action="{{ route('register') }}">
        @csrf
        <x-launchpad::field name="name" :label="__('Your name')" autocomplete="name" autofocus />
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="username" :value="$email" />
        <x-launchpad::field name="password" :label="__('Password')" type="password" autocomplete="new-password" />
        <x-launchpad::field name="password_confirmation" :label="__('Confirm password')" type="password" autocomplete="new-password" />
        <div class="lp-check">
            <input type="checkbox" id="terms" name="terms" value="1" required @checked(old('terms'))>
            <label for="terms">{{ \Launchpad\Support\Trans::html('I agree to the :terms and have read the :privacy.', [
                'terms' => new \Illuminate\Support\HtmlString('<a href="/terms">'.e(__('Terms')).'</a>'),
                'privacy' => new \Illuminate\Support\HtmlString('<a href="/privacy">'.e(__('Privacy Policy')).'</a>'),
            ]) }}</label>
        </div>
        @error('terms')<p class="lp-error">{{ $message }}</p>@enderror
        <x-launchpad::turnstile :key="$turnstileKey" />
        <button class="lp-btn" type="submit">{{ __('Create account') }}</button>
    </form>
    <x-launchpad::social />
</div>
<p class="lp-links"><a href="{{ route('login') }}">{{ __('Already have an account? Sign in') }}</a></p>
@endsection
