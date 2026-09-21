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
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(ClubPaymentMethod $method, array $params): array
    {
        try {
            $session = $this->client($method)->checkout->sessions->create($params);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe checkout could not be created', ['club' => $method->club_id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['payment' => 'Online payment is not available right now. Please choose another way to pay or try again shortly.']);
        }

        return ['id' => (string) $session->id, 'url' => (string) $session->url];
    }

    /**
     * Refund some or all of a card payment. Amounts are in the smallest currency unit.
     */
    public function refund(ClubPaymentMethod $method, string $paymentIntent, int $amountMinor): string
    {
        try {
            $refund = $this->client($method)->refunds->create(['payment_intent' => $paymentIntent, 'amount' => $amountMinor]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe refund failed', ['club' => $method->club_id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['amount' => 'Stripe could not refund this payment: '.$e->getMessage()]);
        }

        return (string) $refund->id;
    }

    private function client(ClubPaymentMethod $method): StripeClient
    {
        return new StripeClient((string) ($method->config['stripe_secret_key'] ?? ''));
    }
}
