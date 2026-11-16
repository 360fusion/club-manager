@extends('launchpad::layouts.base')
@section('title', __('Confirm your password'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Confirm your password') }}</h1>
    <p class="lp-muted">{{ __('This is a secure area. Please confirm your password to carry on.') }}</p>
    <form method="post" action="{{ route('password.confirm.store') }}">
        @csrf
        <x-launchpad::field name="password" :label="__('Password')" type="password" autocomplete="current-password" autofocus />
        <button class="lp-btn" type="submit">{{ __('Confirm') }}</button>
    </form>
</div>
@endsection
