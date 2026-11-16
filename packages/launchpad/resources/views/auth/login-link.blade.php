@extends('launchpad::layouts.base')
@section('title', __('Email me a sign-in link'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Sign in with a link') }}</h1>
    <p class="lp-muted">{{ __('Tell us your email address and we will email you a link that signs you in, no password needed.') }}</p>
    <x-launchpad::status />
    <form method="post" action="{{ route('login.link') }}">
        @csrf
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="username" autofocus />
        <x-launchpad::turnstile :key="$turnstileKey" />
        <button class="lp-btn" type="submit">{{ __('Email me a link') }}</button>
    </form>
</div>
<p class="lp-links"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
@endsection
