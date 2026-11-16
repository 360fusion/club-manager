<?php

namespace Launchpad\Http\Controllers\Billing;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Billing\Money;
use Launchpad\Billing\Plan;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\PromoCode;
use Launchpad\Http\Ui;

class PricingController extends Controller
{
    public function index(Request $request, PlanCatalog $catalog): View|Responsable
    {
        PromoCode::remember($request);
        $period = $request->query('frequency') === Plan::ANNUAL ? Plan::ANNUAL : Plan::MONTHLY;

        return Ui::render('billing.pricing', ['plans' => $catalog->all(), 'catalog' => $catalog, 'period' => $period, 'promo' => PromoCode::pending()], fn () => [
            'period' => $period,
            'promo' => PromoCode::pending(),
            'plans' => collect($catalog->all())->map(fn ($plan) => [
                'key' => $plan->key, 'name' => __($plan->name), 'description' => __($plan->description), 'perSeat' => $plan->perSeat, 'minSeats' => $plan->minSeats, 'free' => $plan->isFree(), 'trialDays' => $plan->trialDays,
                'price' => $plan->amount($period) !== null ? Money::format($plan->amount($period)) : null,
                'held' => $catalog->held($plan),
                'onSale' => $catalog->onSale($plan, $period),
                'href' => $catalog->onSale($plan, $period) ? ($request->user() ? route('checkout.start', [$plan->key, $period]) : route('checkout.account', [$plan->key, $period])) : null,
            ])->values(),
        ]);
    }
}
