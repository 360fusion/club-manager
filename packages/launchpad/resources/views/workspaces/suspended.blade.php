@extends('launchpad::layouts.account')
@section('title', __('Team suspended'))
@section('account')
<div class="lp-card">
    <h1>{{ __('This team is suspended') }}</h1>
    <p>{{ \Launchpad\Support\Trans::html('Support has suspended :team for now, so its pages and API keys are not available.', ['team' => new \Illuminate\Support\HtmlString('<strong>'.e($workspace->name).'</strong>')]) }}</p>
    <p class="lp-muted">{{ __('The team\'s owner can contact support to find out why and what happens next.') }}</p>
    <p>
        @if ($billingUrl)<a class="lp-btn" href="{{ $billingUrl }}">{{ __('Go to billing') }}</a>@endif
        <a class="lp-btn lp-btn--quiet" href="{{ route('workspaces.index') }}">{{ __('Your teams') }}</a>
    </p>
</div>
@endsection
