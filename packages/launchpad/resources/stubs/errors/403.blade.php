@extends('launchpad::layouts.base')
@section('title', __('You cannot open this page'))
@section('noindex', '1')
@section('content')
<div class="lp-card">
    <h1>{{ __('You cannot open this page') }}</h1>
    <p>{{ __('You do not have permission to see this page.') }}</p>
    <p><a class="lp-btn" href="/">{{ __('Go to the home page') }}</a> <a class="lp-btn lp-btn--quiet" href="/contact">{{ __('Contact us') }}</a></p>
</div>
@endsection
