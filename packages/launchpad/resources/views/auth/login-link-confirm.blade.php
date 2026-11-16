@extends('launchpad::layouts.base')
@section('title', __('Sign in'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Sign in') }}</h1>
    <p>{{ __('Press the button to finish signing in on this device.') }}</p>
    <form method="post" action="{{ $action }}">
        @csrf
        <button class="lp-btn" type="submit">{{ __('Sign in') }}</button>
    </form>
</div>
@endsection
