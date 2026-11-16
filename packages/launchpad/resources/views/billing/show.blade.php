@extends('launchpad::layouts.account')
@section('title', __('Billing'))
@section('account')
@if ($paymentProblem)
    <div class="lp-alert lp-alert--error" role="alert">
        <strong>{{ __('Your last payment did not go through.') }}</strong> {{ __('Until it does, :team has the features of the free plan.', ['team' => $workspace->name]) }}
        <form class="lp-inline" method="post" action="{{ route('workspace.billing.portal', $workspace->slug) }}">@csrf <button class="lp-btn" type="submit">{{ __('Update your card') }}</button></form>
    </div>
@endif
<div class="lp-card">
    <h1>{{ __('Billing for :team', ['team' => $workspace->name]) }}</h1>
    <h2>{{ __('Your plan') }}</h2>
    <p><strong>{{ __($plan->name) }}</strong>
        @if ($period)
            @php($billed = $period === 'annual' ? __('billed yearly') : __('billed monthly'))
            <span class="lp-muted">({{ $plan->perSeat ? __(':billed, :seats', ['billed' => $billed, 'seats' => trans_choice('{1} :count seat|[2,*] :count seats', $seats)]) : $billed }})</span>
        @endif
    </p>
    @if ($subscription?->onTrial())
        <p class="lp-alert">{{ __('Your free trial ends on :date. After that your card is charged and the plan continues; cancel before then to pay nothing.', ['date' => $subscription->trial_ends_at->isoFormat('ll')]) }}</p>
    @endif
    @if ($overview['ends_at'])
        <p class="lp-alert">{{ __('Your plan ends on :date, then :team moves to the free plan.', ['date' => $overview['ends_at']->isoFormat('ll'), 'team' => $workspace->name]) }}</p>
    @elseif ($overview['renews_at'])
        <p class="lp-muted">{{ __('Renews on :date.', ['date' => $overview['renews_at']->isoFormat('ll')]) }}</p>
    @endif
    @if ($discount = $overview['discount'] ?? null)
        <p>{{ __('Discount: :description.', ['description' => $discount['description']]) }}
            @if ($discount['code']){{ __('Applied with the promo code :code.', ['code' => $discount['code']]) }}@endif
            @if ($discount['ends_at']){{ __('It ends on :date.', ['date' => $discount['ends_at']->isoFormat('ll')]) }}@endif</p>
    @endif
    @if ($overview['card'])<p>{{ __('Card: :brand ending :last_four', ['brand' => ucfirst($overview['card']['brand']), 'last_four' => $overview['card']['last_four']]) }}</p>@endif
    @if ($workspace->hasStripeId())
        <form class="lp-inline" method="post" action="{{ route('workspace.billing.portal', $workspace->slug) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Card, address and VAT number') }}</button></form>
    @endif
    @if ($period && ! $overview['ends_at'])
        <form class="lp-inline" method="post" action="{{ route('workspace.billing.cancel', $workspace->slug) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Cancel plan') }}</button></form>
    @endif
</div>
@if ($meters)
<div class="lp-card">
    <h2>{{ __('Usage') }}</h2>
    <ul class="lp-usage">
    @foreach ($meters as $meter)
        <li>
            @if ($meter['limit'] !== null)
                {{ $meter['monthly'] ? __(':used of :limit :label this month', $meter) : __(':used of :limit :label', $meter) }}
                <meter min="0" max="{{ max(1, $meter['limit']) }}" value="{{ min($meter['used'], $meter['limit']) }}" high="{{ (int) ceil($meter['limit'] * 0.8) }}" aria-label="{{ $meter['label'] }}">{{ $meter['used'] }}</meter>
                @if ($meter['billed'])<br><span class="lp-muted">{{ $meter['cost'] ? __(':extra extra so far, about :cost (charged on your next invoice)', $meter) : __(':extra extra so far (charged on your next invoice)', $meter) }}</span>@endif
            @else
                {{ $meter['monthly'] ? __(':used :label this month', $meter) : __(':used :label', $meter) }}
            @endif
        </li>
    @endforeach
    </ul>
</div>
@endif
<div class="lp-card">
    <h2>{{ __('Change plan') }}</h2>
    <ul>
    @foreach ($plans as $option)
        @continue($option->isFree())
        @foreach ($option->periods() as $p)
            @if ($catalog->onSale($option, $p) && ! ($option->key === $plan->key && $p === $period))
                <li><a href="{{ route('workspace.billing.change', [$workspace->slug, $option->key, $p]) }}">{{ $p === 'annual' ? __(':plan, yearly', ['plan' => __($option->name)]) : __(':plan, monthly', ['plan' => __($option->name)]) }}</a>
                    <span class="lp-muted">{{ \Launchpad\Billing\Money::format($option->amount($p)) }}</span></li>
            @endif
        @endforeach
    @endforeach
    </ul>
</div>
<div class="lp-card">
    <h2>{{ __('Invoices') }}</h2>
    @forelse ($overview['invoices'] as $invoice)
        <p>{{ $invoice['date'] }}: {{ $invoice['total'] }} {{ $invoice['paid'] ? '' : __('(unpaid)') }} @if ($invoice['url'])<a href="{{ $invoice['url'] }}" rel="noopener">{{ __('View :number', ['number' => $invoice['number']]) }}</a>@endif @if (! empty($invoice['pdf']))<a href="{{ $invoice['pdf'] }}" rel="noopener">{{ __('PDF') }}</a>@endif</p>
    @empty
        <p class="lp-muted">{{ __('No invoices yet.') }}</p>
    @endforelse
</div>
@endsection
