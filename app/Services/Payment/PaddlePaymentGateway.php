<?php

namespace App\Services\Payment;

use Illuminate\Database\Eloquent\Model;

class PaddlePaymentGateway implements PaymentGatewayInterface
{
    public function getProviderName(): string
    {
        return 'paddle';
    }

    public function createCheckoutSession(Model $customer, string $priceId, array $options = []): string
    {
        // Paddle Cashier checkout URL or transaction setup
        $checkout = $customer->subscribe($priceId, 'default')
            ->returnTo(route('billing.index', ['status' => 'success']));

        return $checkout->url();
    }

    public function createCustomerPortalSession(Model $customer, string $returnUrl): string
    {
        $subscription = $customer->subscription('default');
        if ($subscription) {
            return $subscription->paymentMethodUpdateUrl();
        }

        return $returnUrl;
    }

    public function getSubscriptionStatus(Model $customer, string $name = 'default'): ?string
    {
        $subscription = $customer->subscription($name);

        if (!$subscription) {
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
