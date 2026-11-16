@extends('launchpad::layouts.base')
@section('title', __('Change plan'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Change plan') }}</h1>
    <p>{{ \Launchpad\Support\Trans::html('From :from to :to (:period).', [
        'from' => new \Illuminate\Support\HtmlString('<strong>'.e(__($from->name)).'</strong>'),
        'to' => new \Illuminate\Support\HtmlString('<strong>'.e(__($to->name)).'</strong>'),
        'period' => $toPeriod === 'annual' ? __('billed yearly') : __('billed monthly'),
    ]) }}</p>
    @if (! $hasSubscription)
        <p>{{ __('You will pay for it on the next page.') }}</p>
    @elseif ($timing === \Launchpad\Billing\PlanChange::NOW)
        <p>{{ \Launchpad\Support\Trans::html('The change starts :now. You pay only the difference for the time left in this period.', ['now' => new \Illuminate\Support\HtmlString('<strong>'.e(__('now')).'</strong>')]) }}</p>
    @else
        <p>{{ \Launchpad\Support\Trans::html('The change starts at your :renewal, so you keep what you have paid for until then.', ['renewal' => new \Illuminate\Support\HtmlString('<strong>'.e(__('next renewal')).'</strong>')]) }}</p>
    @endif
    <form method="post" action="{{ route('workspace.billing.change.start', [$workspace->slug, $to->key, $toPeriod]) }}">
        @csrf
        <button class="lp-btn" type="submit">{{ __('Carry on to payment') }}</button>
        <a class="lp-btn lp-btn--quiet" href="{{ route('workspace.billing', $workspace->slug) }}">{{ __('Not now') }}</a>
    </form>
</div>
@endsection
