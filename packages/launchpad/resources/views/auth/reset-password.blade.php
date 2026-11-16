@extends('launchpad::layouts.base')
@section('title', __('Choose a new password'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Choose a new password') }}</h1>
    <form method="post" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="username" :value="$email" />
        <x-launchpad::field name="password" :label="__('New password')" type="password" autocomplete="new-password" autofocus />
        <x-launchpad::field name="password_confirmation" :label="__('Confirm new password')" type="password" autocomplete="new-password" />
        <button class="lp-btn" type="submit">{{ __('Save password') }}</button>
    </form>
</div>
@endsection
