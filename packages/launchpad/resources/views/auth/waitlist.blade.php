@extends('launchpad::layouts.base')
@section('title', __('Join the waitlist'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Join the waitlist') }}</h1>
    <p class="lp-muted">{{ __('Sign-up is by invitation for now. Leave your address and we will email you when there is room.') }}</p>
    <x-launchpad::status />
    <form method="post" action="{{ route('waitlist') }}">
        @csrf
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="email" autofocus />
        <x-launchpad::field name="name" :label="__('Your name (optional)')" autocomplete="name" :required="false" />
        <x-launchpad::turnstile :key="$turnstileKey" />
        <button class="lp-btn" type="submit">{{ __('Join') }}</button>
    </form>
</div>
<p class="lp-links"><a href="{{ route('login') }}">{{ __('Already have an account? Sign in') }}</a></p>
@endsection
