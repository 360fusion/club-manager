@extends('launchpad::layouts.base')
@section('title', __('Slow down a little'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('Slow down a little') }}</h1>
    <p>{{ __('There have been a lot of requests from you in a short time. Wait a minute and try again.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
