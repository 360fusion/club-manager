@extends('launchpad::layouts.base')
@section('title', __('Something went wrong'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('Something went wrong') }}</h1>
    <p>{{ __('It is our fault, not yours. Please try again in a little while.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
