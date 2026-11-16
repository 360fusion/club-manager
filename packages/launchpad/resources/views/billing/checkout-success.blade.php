@extends('launchpad::layouts.base')
@section('title', __('Thank you'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Thank you') }}</h1>
    <p>{{ __('Your payment is with us. Your plan appears on the billing page within a minute or so, as soon as Stripe has told us.') }}</p>
    @if ($workspace && Route::has('workspace.billing'))<a class="lp-btn" href="{{ route('workspace.billing', $workspace->slug) }}">{{ __('Go to billing') }}</a>@endif
</div>
@endsection
