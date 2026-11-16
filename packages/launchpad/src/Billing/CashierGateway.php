<?php

namespace Launchpad\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Launchpad\Models\Workspace;
use Stripe\Exception\ApiErrorException;

/** The real Gateway, through Laravel Cashier. Not run in the package's tests (they use Testing\FakeGateway); see docs/stripe-setup.md for the live check. */
class CashierGateway implements Gateway
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    public function checkoutUrl(Workspace $workspace, string $priceId, int $quantity, string $successUrl, string $cancelUrl, int $trialDays = 0, ?string $promoCode = null): string
    {
        $subscription = $workspace->newSubscription((string) config('launchpad.billing.subscription_type', 'default'), $priceId)
            ->quantity(max(1, $quantity));

        // the plan's metered prices go on the same subscription, charged for what is reported beyond the quota
        foreach (($plan = app(PlanCatalog::class)->byPrice($priceId)[0] ?? null) ? MeteredBilling::prices($plan) : [] as $metered) {
            $subscription->meteredPrice($metered);
        }

        // Stripe refuses a code given up front together with the box where the person types one
        ($promotionId = $promoCode ? $this->promotionCodeId($promoCode) : null) ? $subscription->withPromotionCode($promotionId) : $subscription->allowPromotionCodes();

        if ($trialDays > 0) {
            $subscription->trialDays($trialDays);
        }

        $checkout = $subscription->checkout(array_merge([
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], Vat::checkoutOptions()));

        return $checkout->url;
    }

    /** The id of the active promotion code of that name, or null (a mistyped or finished code does not stop the checkout) */
    private function promotionCodeId(string $code): ?string
    {
        try {
            return Cashier::stripe()->promotionCodes->all(['code' => $code, 'active' => true, 'limit' => 1])->data[0]->id ?? null;
        } catch (ApiErrorException) {
            return null;
        }
    }

    public function promotionCodes(): array
    {
        return collect(Cashier::stripe()->promotionCodes->all(['limit' => 100, 'expand' => ['data.promotion.coupon']])->data)->map(function ($promo) {
            $coupon = $promo->promotion->coupon;

            return [
                'id' => $promo->id, 'code' => $promo->code, 'redeemed' => (int) $promo->times_redeemed, 'max' => $promo->max_redemptions, 'active' => (bool) $promo->active,
                'discount' => PromoCode::describe($coupon->percent_off, $coupon->amount_off, (string) $coupon->duration, $coupon->duration_in_months),
                'expires_at' => $promo->expires_at ? Carbon::createFromTimestamp($promo->expires_at) : null,
            ];
        })->all();
    }

    public function createPromotionCode(string $code, array $discount, ?int $maxRedemptions, ?CarbonInterface $expiresAt): string
    {
        $coupon = Cashier::stripe()->coupons->create($discount + ['name' => $code] + (isset($discount['amount_off']) ? ['currency' => config('launchpad.billing.currency')] : []));

        return Cashier::stripe()->promotionCodes->create(array_filter([
            'promotion' => ['type' => 'coupon', 'coupon' => $coupon->id], 'code' => $code,
            'max_redemptions' => $maxRedemptions, 'expires_at' => $expiresAt?->getTimestamp(),
        ]))->id;
    }

    public function deactivatePromotionCode(string $id): void
    {
        Cashier::stripe()->promotionCodes->update($id, ['active' => false]);
    }

    public function portalUrl(Workspace $workspace, string $returnUrl, ?array $flow = null): string
    {
        $options = [];

        if ($flow !== null) {
            $options['flow_data'] = $this->flowData($workspace, $flow);
        }

        return $workspace->billingPortalUrl($returnUrl, $options);
    }

    /** @param  array<string, mixed>  $flow */
    private function flowData(Workspace $workspace, array $flow): array
    {
        $subscription = $workspace->subscription((string) config('launchpad.billing.subscription_type', 'default'));
        abort_if($subscription === null, 404);

        if ($flow['type'] === 'subscription_cancel') {
            return ['type' => 'subscription_cancel', 'subscription_cancel' => ['subscription' => $subscription->stripe_id]];
        }

        return [
            'type' => 'subscription_update_confirm',
            'subscription_update_confirm' => [
                'subscription' => $subscription->stripe_id,
                'items' => [[
                    // the plan's own item, not a metered one beside it
                    'id' => (app(PlanCatalog::class)->ofSubscription($subscription)[3] ?? $subscription->items()->first())->stripe_id,
                    'price' => $flow['price'],
                    'quantity' => $flow['quantity'] ?? $subscription->quantity ?? 1,
                ]],
            ],
        ];
    }

    public function overview(Workspace $workspace): array
    {
        $type = (string) config('launchpad.billing.subscription_type', 'default');
        $subscription = $workspace->subscription($type);
        $renewsAt = null;
        $discount = null;
        $invoices = [];

        if ($workspace->hasStripeId()) {
            try {
                $invoices = $workspace->invoices()->take(12)->map(fn ($invoice) => [
                    'number' => (string) $invoice->number,
                    'date' => $invoice->date()->toFormattedDateString(),
                    'total' => $invoice->total(),
                    'url' => $invoice->asStripeInvoice()->hosted_invoice_url ?? null,
                    'pdf' => $invoice->asStripeInvoice()->invoice_pdf ?? null,
                    'paid' => $invoice->paid(),
                ])->all();

                if ($subscription && ! $subscription->canceled()) {
                    $stripe = $subscription->asStripeSubscription();
                    $end = $stripe->items->data[0]->current_period_end ?? $stripe->current_period_end ?? null;
                    $renewsAt = $end ? Carbon::createFromTimestamp($end) : null;

                    if ($applied = $subscription->discount()) {
                        $coupon = $applied->coupon();
                        $discount = [
                            'description' => PromoCode::describe($coupon->percentOff(), $coupon->rawAmountOff(), $coupon->duration(), $coupon->asStripeCoupon()->duration_in_months),
                            'code' => $applied->promotionCode()?->code,
                            'ends_at' => $applied->end(),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                report($e); // the page still shows the plan: it does not need Stripe to say what it is
            }
        }

        return [
            'card' => $workspace->pm_last_four ? ['brand' => (string) $workspace->pm_type, 'last_four' => $workspace->pm_last_four] : null,
            'invoices' => $invoices,
            'renews_at' => $renewsAt,
            'ends_at' => $subscription?->ends_at,
            'discount' => $discount,
        ];
    }

    public function reportUsage(Workspace $workspace, string $event, int $quantity, string $identifier): void
    {
        $workspace->reportMeterEvent($event, $quantity, ['identifier' => $identifier]);
    }

    public function cancelSubscriptions(Workspace $workspace): void
    {
        foreach ($workspace->subscriptions()->get() as $subscription) {
            if (! $subscription->ended()) {
                $subscription->cancelNow();
            }
        }
    }

    public function health(): array
    {
        $checks = [];
        $stripe = Cashier::stripe();

        foreach ($this->catalog->all() as $plan) {
            foreach ($plan->periods() as $period) {
                $name = "Price {$plan->name} ({$period})";

                try {
                    $price = $stripe->prices->retrieve($plan->priceId($period));
                    $ok = $price->active && strtolower($price->currency) === strtolower((string) config('cashier.currency'));
                    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $ok ? 'active, '.$price->currency : 'inactive or in another currency than CASHIER_CURRENCY'];
                } catch (\Throwable $e) {
                    $checks[] = ['name' => $name, 'ok' => false, 'detail' => 'not found in this Stripe account'];
                }
            }
        }

        try {
            $listening = collect($stripe->webhookEndpoints->all(['limit' => 100])->data)
                ->filter(fn ($endpoint) => str_ends_with($endpoint->url, '/stripe/webhook') && $endpoint->status === 'enabled');
            $events = $listening->flatMap(fn ($endpoint) => $endpoint->enabled_events)->all();
            $missing = in_array('*', $events, true) ? [] : array_values(array_diff(StripeEvents::REQUIRED, $events));
            $checks[] = [
                'name' => 'Webhook endpoint',
                'ok' => $listening->isNotEmpty() && $missing === [],
                'detail' => $listening->isEmpty() ? 'no enabled endpoint ending /stripe/webhook' : ($missing === [] ? 'listens to every required event' : 'missing: '.implode(', ', $missing)),
            ];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Webhook endpoint', 'ok' => false, 'detail' => 'could not be read: '.$e->getMessage()];
        }

        try {
            $portal = $stripe->billingPortal->configurations->all(['is_active' => true, 'limit' => 1]);
            $checks[] = ['name' => 'Customer portal', 'ok' => count($portal->data) > 0, 'detail' => count($portal->data) > 0 ? 'configured' : 'no active configuration: save one in Stripe > Settings > Billing > Customer portal'];
        } catch (\Throwable $e) {
            $checks[] = ['name' => 'Customer portal', 'ok' => false, 'detail' => 'could not be read: '.$e->getMessage()];
        }

        return $checks;
    }
}
