<?php

namespace Launchpad\Billing;

use Carbon\CarbonInterface;
use Launchpad\Models\Workspace;

/**
 * Everything the app asks of Stripe, in one place. CashierGateway is the real one; Testing\FakeGateway stands in for it in tests, so no test
 * touches the network. (The app never moves money itself: Stripe's checkout and customer portal do.)
 */
interface Gateway
{
    /** The address of a Stripe Checkout page for a subscription to $priceId, free for $trialDays first when that is more than 0, with the promotion code $promoCode applied when Stripe has an active one of that name (else the page lets the person type one) */
    public function checkoutUrl(Workspace $workspace, string $priceId, int $quantity, string $successUrl, string $cancelUrl, int $trialDays = 0, ?string $promoCode = null): string;

    /**
     * The address of Stripe's customer portal. $flow starts it on one task: ['type' => 'subscription_cancel'], or ['type' =>
     * 'subscription_update_confirm', 'price' => 'price_...', 'quantity' => 1].
     *
     * @param  array<string, mixed>|null  $flow
     */
    public function portalUrl(Workspace $workspace, string $returnUrl, ?array $flow = null): string;

    /**
     * What the billing page shows.
     *
     * @return array{card: ?array{brand: string, last_four: string}, invoices: list<array{number: string, date: string, total: string, url: ?string, pdf?: ?string, paid: bool}>, renews_at: ?CarbonInterface, ends_at: ?CarbonInterface, discount?: ?array{description: string, code: ?string, ends_at: ?CarbonInterface}}
     */
    public function overview(Workspace $workspace): array;

    /**
     * The promotion codes of the Stripe account, newest first.
     *
     * @return list<array{id: string, code: string, discount: string, redeemed: int, max: ?int, expires_at: ?CarbonInterface, active: bool}>
     */
    public function promotionCodes(): array;

    /**
     * Makes a coupon and a promotion code for it; returns the code's id.
     *
     * @param  array{percent_off?: float, amount_off?: int, duration: string, duration_in_months?: int}  $discount  amount_off is in the smallest currency unit
     */
    public function createPromotionCode(string $code, array $discount, ?int $maxRedemptions, ?CarbonInterface $expiresAt): string;

    /** Stops a promotion code being used (what has been bought with it is not touched) */
    public function deactivatePromotionCode(string $id): void;

    /** Reports use of a Stripe meter for the workspace; $identifier lets Stripe drop a report it already has */
    public function reportUsage(Workspace $workspace, string $event, int $quantity, string $identifier): void;

    /** Ends every subscription of the workspace now (the account that paid for them is being closed) */
    public function cancelSubscriptions(Workspace $workspace): void;

    /**
     * Checks of the Stripe account against what the app needs.
     *
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public function health(): array;
}
