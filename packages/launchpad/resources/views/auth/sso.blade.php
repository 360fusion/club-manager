@extends('launchpad::layouts.base')
@section('title', __('Sign in with single sign-on'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Sign in with single sign-on') }}</h1>
    <p class="lp-muted">{{ __('If your team signs in with your company account, enter your work email address and you will be sent to your company to sign in.') }}</p>
    <form method="post" action="{{ route('sso.start') }}">
        @csrf
        <x-launchpad::field name="email" :label="__('Work email address')" type="email" autocomplete="username" :value="$email" autofocus />
        <button class="lp-btn" type="submit">{{ __('Continue') }}</button>
    </form>
</div>
<p class="lp-links"><a href="{{ route('login') }}">{{ __('Sign in with a password instead') }}</a></p>
@endsection
