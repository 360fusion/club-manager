@extends('launchpad::layouts.base')
@section('title', __('Payment cancelled'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Nothing was charged') }}</h1>
    <p>{{ __('You left the payment page, so your plan has not changed.') }}</p>
    <a class="lp-btn" href="{{ route('pricing') }}">{{ __('Back to pricing') }}</a>
</div>
@endsection
