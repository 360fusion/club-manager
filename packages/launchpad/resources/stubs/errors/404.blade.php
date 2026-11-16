@extends('launchpad::layouts.base')
@section('title', __('Page not found'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('Page not found') }}</h1>
    <p>{{ __('We could not find that page. It may have moved, or the address may have a typo.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
