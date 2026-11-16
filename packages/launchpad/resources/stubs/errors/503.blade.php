@extends('launchpad::layouts.base')
@section('title', __('Back soon'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('Back soon') }}</h1>
    <p>{{ __('We are making an improvement and will be back shortly.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
