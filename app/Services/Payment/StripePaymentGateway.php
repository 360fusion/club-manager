<?php

namespace App\Services\Payment;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function getProviderName(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(Model $customer, string $priceId, array $options = []): string
    {
        // Stripe Cashier checkout session creation
        $checkout = $customer->checkout([$priceId => 1], array_merge([
            'success_url' => route('billing.index', ['clubSlug' => $customer->slug, 'status' => 'success']),
            'cancel_url' => route('billing.index', ['clubSlug' => $customer->slug, 'status' => 'cancelled']),
        ], $options));

        return $checkout->url;
    }

    public function createCustomerPortalSession(Model $customer, string $returnUrl): string
    {
        return $customer->billingPortalUrl($returnUrl);
    }

    public function getSubscriptionStatus(Model $customer, string $name = 'default'): ?string
    {
        $subscription = $customer->subscription($name);

        if (! $subscription) {
            return 'none';
        }

        if ($subscription->onTrial()) {
            return 'trialing';
        }

        if ($subscription->active()) {
            return 'active';
        }

        if ($subscription->canceled()) {
            return 'canceled';
        }

        return 'inactive';
    }

    public function cancelSubscription(Model $customer, string $name = 'default'): bool
    {
        $subscription = $customer->subscription($name);
        if ($subscription) {
            $subscription->cancel();

            return true;
        }

        return false;
    }
}
