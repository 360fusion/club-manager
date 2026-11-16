@extends('launchpad::layouts.base')
@section('title', __('Pricing'))
@section('description', __('Simple plans. Change or cancel whenever you like.'))
@section('main-class', 'lp-main--wide lp-main--pricing')
@section('content')
<div class="lp-card">
    <h1>{{ __('Pricing') }}</h1>
    @error('plan')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    @if ($promo)<p class="lp-alert" role="status">{{ \Launchpad\Support\Trans::html('Promo code :code will be applied when you check out, if it is still valid.', ['code' => new \Illuminate\Support\HtmlString('<strong>'.e($promo).'</strong>')]) }}</p>@endif
    <p role="group" aria-label="{{ __('Billing period') }}">
        <a class="lp-btn {{ $period === 'monthly' ? '' : 'lp-btn--quiet' }}" href="{{ route('pricing', ['frequency' => 'monthly']) }}" @if ($period === 'monthly') aria-current="true" @endif>{{ __('Monthly') }}</a>
        <a class="lp-btn {{ $period === 'annual' ? '' : 'lp-btn--quiet' }}" href="{{ route('pricing', ['frequency' => 'annual']) }}" @if ($period === 'annual') aria-current="true" @endif>{{ __('Yearly') }}</a>
    </p>
</div>
<div class="lp-plans">
@foreach ($plans as $plan)
    <section class="lp-card" aria-labelledby="plan-{{ $plan->key }}">
        <h2 id="plan-{{ $plan->key }}">{{ __($plan->name) }}</h2>
        <p class="lp-muted">{{ __($plan->description) }}</p>
        <p class="lp-price">
            @if ($plan->isFree())
                {{ __('Free') }}
            @elseif ($plan->amount($period) !== null)
                {{ \Launchpad\Billing\Money::format($plan->amount($period)) }}
                <span class="lp-muted">{{ match (true) {
                    $plan->perSeat && $period === 'annual' => __('per person a year'),
                    $plan->perSeat => __('per person a month'),
                    $period === 'annual' => __('a year'),
                    default => __('a month'),
                } }}</span>
                @if ($plan->perSeat && $plan->minSeats > 1)<br><span class="lp-muted">{{ trans_choice('{1} Minimum :count seat|[2,*] Minimum :count seats', $plan->minSeats) }}</span>@endif
            @endif
        </p>
        @if ($plan->trialDays > 0 && ! $plan->isFree())<p><strong>{{ trans_choice('{1} :count-day free trial|[2,*] :count-day free trial', $plan->trialDays) }}</strong></p>@endif
        @if ($plan->isFree())
            <a class="lp-btn lp-btn--quiet" href="{{ route('register') }}">{{ __('Start free') }}</a>
        @elseif ($catalog->held($plan))
            <span class="lp-btn lp-btn--quiet" aria-disabled="true">{{ __('Coming soon') }}</span>
        @elseif ($catalog->onSale($plan, $period))
            <a class="lp-btn" href="{{ auth()->check() ? route('checkout.start', [$plan->key, $period]) : route('checkout.account', [$plan->key, $period]) }}">{{ $plan->trialDays > 0 ? __('Start free trial') : __('Choose :plan', ['plan' => __($plan->name)]) }}</a>
        @else
            <span class="lp-muted">{{ __('Not available for this period.') }}</span>
        @endif
    </section>
@endforeach
</div>
@endsection
