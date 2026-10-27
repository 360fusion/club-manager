<?php

namespace App\Services\Payment;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;

class PaddlePaymentGateway implements PaymentGatewayInterface
{
    public function getProviderName(): string
    {
        return 'paddle';
    }

    /**
     * @param  Club  $customer  The billable model is always a club.
     */
    public function createCheckoutSession(Model $customer, string $priceId, array $options = []): string
    {
        // Paddle Cashier checkout URL or transaction setup
        $checkout = $customer->subscribe($priceId, 'default')
            ->returnTo(route('admin.memberships.index', ['clubSlug' => $customer->slug, 'status' => 'success']));

        return $checkout->url();
    }

    /**
     * @param  Club  $customer  The billable model is always a club.
     */
    public function createCustomerPortalSession(Model $customer, string $returnUrl): string
    {
        $subscription = $customer->subscription('default');
        if ($subscription) {
            return $subscription->paymentMethodUpdateUrl();
        }

        return $returnUrl;
    }

    /**
     * @param  Club  $customer  The billable model is always a club.
     */
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

    /**
     * @param  Club  $customer  The billable model is always a club.
     */
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
