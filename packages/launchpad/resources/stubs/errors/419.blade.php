@extends('launchpad::layouts.base')
@section('title', __('This page has expired'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('This page has expired') }}</h1>
    <p>{{ __('The page was open for too long. Go back, reload it and try again.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
