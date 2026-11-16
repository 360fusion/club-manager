@extends('launchpad::layouts.base')
@section('title', __('Two-factor sign-in'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Two-factor sign-in') }}</h1>
    <p class="lp-muted">{{ __('Enter the code from your authenticator app.') }}</p>
    <form method="post" action="{{ route('two-factor.verify') }}">
        @csrf
        <x-launchpad::field name="code" :label="__('Code')" autocomplete="one-time-code" inputmode="numeric" autofocus :required="false" />
        <button class="lp-btn" type="submit">{{ __('Sign in') }}</button>
    </form>
</div>
<div class="lp-card">
    <h2>{{ __('Lost your phone?') }}</h2>
    <form method="post" action="{{ route('two-factor.verify') }}">
        @csrf
        <x-launchpad::field name="recovery_code" :label="__('Recovery code')" autocomplete="off" :required="false" />
        <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Use a recovery code') }}</button>
    </form>
</div>
@endsection
