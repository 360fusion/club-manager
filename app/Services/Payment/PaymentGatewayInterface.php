<?php

namespace App\Services\Payment;

use Illuminate\Database\Eloquent\Model;

interface PaymentGatewayInterface
{
    /**
     * Get the provider key (e.g. 'stripe' or 'paddle').
     */
    public function getProviderName(): string;

    /**
     * Create a checkout session URL for subscription or one-time payment.
     */
    public function createCheckoutSession(Model $customer, string $priceId, array $options = []): string;

    /**
     * Create a customer portal redirect URL.
     */
    public function createCustomerPortalSession(Model $customer, string $returnUrl): string;

    /**
     * Get active subscription status for a billable entity.
     */
    public function getSubscriptionStatus(Model $customer, string $name = 'default'): ?string;

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(Model $customer, string $name = 'default'): bool;
}
