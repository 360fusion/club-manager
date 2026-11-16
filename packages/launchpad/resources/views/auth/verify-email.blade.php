@extends('launchpad::layouts.base')
@section('title', __('Check your inbox'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Check your inbox') }}</h1>
    <p>{{ \Launchpad\Support\Trans::html('We have sent a link to :email. Open it to confirm your address.', ['email' => new \Illuminate\Support\HtmlString('<strong>'.e($email).'</strong>')]) }}</p>
    @if (session('status') === 'verification-link-sent')
        <div class="lp-alert" role="status">{{ __('A new link is on its way.') }}</div>
    @endif
    <form class="lp-inline" method="post" action="{{ route('verification.send') }}">@csrf <button class="lp-btn" type="submit">{{ __('Send the link again') }}</button></form>
    <p class="lp-muted">{{ __('Wrong address? Sign out and create the account again.') }}</p>
</div>
@endsection
