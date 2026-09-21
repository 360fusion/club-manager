<?php

namespace App\Services\Payment;

use App\Models\ClubPaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * The only place the app talks to Stripe's API, always with the lodge's own secret key so the
 * money goes straight to the lodge. Tests replace this class, so no test calls Stripe.
 */
class StripeGateway
{
    /**
     * Start a hosted Stripe Checkout page.
     *
     * With a connected account, the page is created on that lodge's own Stripe account using the platform's key.
     *
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(ClubPaymentMethod $method, array $params, ?string $connectedAccount = null): array
    {
        try {
            $session = $this->client($method, $connectedAccount)->checkout->sessions->create($params, $connectedAccount ? ['stripe_account' => $connectedAccount] : []);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe checkout could not be created', ['club' => $method->club_id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['payment' => 'Online payment is not available right now. Please choose another way to pay or try again shortly.']);
        }

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    /**
     * Refund some or all of a card payment. Amounts are in the smallest currency unit.
     */
    public function refund(ClubPaymentMethod $method, string $paymentIntent, int $amountMinor, ?string $connectedAccount = null): string
    {
        try {
            // On a connected account the platform's commission is returned in proportion to what is refunded.
            $refund = $this->client($method, $connectedAccount)->refunds->create(
                ['payment_intent' => $paymentIntent, 'amount' => $amountMinor] + ($connectedAccount ? ['refund_application_fee' => true] : []),
                $connectedAccount ? ['stripe_account' => $connectedAccount] : [],
            );
        } catch (ApiErrorException $e) {
            Log::warning('Stripe refund failed', ['club' => $method->club_id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['amount' => 'Stripe could not refund this payment: '.$e->getMessage()]);
        }

        return (string) $refund->id;
    }

    private function client(ClubPaymentMethod $method, ?string $connectedAccount = null): StripeClient
    {
        return new StripeClient((string) ($connectedAccount ? config('platform_payments.stripe_secret') : ($method->config['stripe_secret_key'] ?? '')));
    }
}
