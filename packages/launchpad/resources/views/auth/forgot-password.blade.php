@extends('launchpad::layouts.base')
@section('title', __('Forgot your password'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Forgot your password?') }}</h1>
    <p class="lp-muted">{{ __('Tell us your email address and we will email you a link to choose a new one.') }}</p>
    <x-launchpad::status />
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="username" autofocus />
        <x-launchpad::turnstile :key="$turnstileKey" />
        <button class="lp-btn" type="submit">{{ __('Email me a link') }}</button>
    </form>
</div>
<p class="lp-links"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
@endsection
