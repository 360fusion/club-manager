<?php

namespace Launchpad\Http\Controllers\Billing;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Turnstile;
use Launchpad\Accounts\VerificationGrace;
use Launchpad\Billing\PlanCatalog;
use Launchpad\Billing\PromoCode;
use Launchpad\Http\Controllers\Auth\RegisteredUserController;
use Launchpad\Http\Ui;

/**
 * Buying a plan without an account first: the visitor makes the account on the way, then goes straight to Stripe. The address is not yet
 * proved, so the account is given the grace period of an account that was there before verification was required: it can pay now and must
 * verify within it.
 */
class PlanSignupController extends RegisteredUserController
{
    public function show(Request $request, string $plan, string $frequency, PlanCatalog $catalog): View|Responsable|RedirectResponse
    {
        $this->plan($catalog, $plan, $frequency);
        PromoCode::remember($request);

        if ($request->user()) {
            return redirect()->route('checkout.start', [$plan, $frequency]);
        }

        return Ui::render('billing.plan-account', [
            'plan' => $catalog->find($plan),
            'frequency' => $frequency,
            'turnstileKey' => Turnstile::siteKey(),
            'email' => $request->query('email'),
        ], fn () => [
            'plan' => ['key' => $plan, 'name' => __($catalog->find($plan)->name)],
            'frequency' => $frequency,
            'turnstileKey' => Turnstile::siteKey(),
            'email' => $request->query('email'),
        ]);
    }

    public function storeAccount(Request $request, string $plan, string $frequency, PlanCatalog $catalog, AccountRegistration $accounts, Turnstile $turnstile): RedirectResponse
    {
        $this->plan($catalog, $plan, $frequency);

        $user = $this->createAccount($request, $accounts, $turnstile);
        $user->forceFill(['verify_by' => VerificationGrace::deadline()])->save();

        return redirect()->route('checkout.start', [$plan, $frequency]);
    }

    private function plan(PlanCatalog $catalog, string $plan, string $frequency): void
    {
        $found = $catalog->find($plan);

        abort_unless($found && $catalog->onSale($found, $frequency), 404);
    }
}
