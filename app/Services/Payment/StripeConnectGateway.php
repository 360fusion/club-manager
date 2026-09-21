<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\OAuth;
use Stripe\Stripe;
use Stripe\StripeClient;

/**
 * The only place the app talks to Stripe Connect with the platform's own key: connecting a lodge's existing
 * Stripe account, creating a new one for it, and checking its status. Tests replace this class.
 */
class StripeConnectGateway
{
    /**
     * Where to send a lodge officer to approve connecting their existing Stripe account.
     */
    public function authorizeUrl(string $state, string $redirectUri): string
    {
        return 'https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('platform_payments.connect_client_id'),
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Swap the one-time code Stripe sent back for the connected account's id.
     */
    public function accountIdForCode(string $code): string
    {
        $this->useKey();

        try {
            $response = OAuth::token(['grant_type' => 'authorization_code', 'code' => $code]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Connect code was not accepted', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages(['stripe' => 'Stripe did not accept that connection. Please try again.']);
        }

        return (string) $response->stripe_user_id;
    }

    public function disconnect(string $accountId): void
    {
        $this->useKey();

        try {
            OAuth::deauthorize(['client_id' => config('platform_payments.connect_client_id'), 'stripe_user_id' => $accountId]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Connect disconnect failed', ['account' => $accountId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Create a new Stripe account for a lodge that has none.
     */
    public function createExpressAccount(string $country, ?string $email, string $businessName): string
    {
        try {
            $account = $this->client()->accounts->create([
                'type' => 'express',
                'country' => strtoupper($country),
                'email' => $email ?: null,
                'business_profile' => ['name' => $businessName],
                'capabilities' => ['card_payments' => ['requested' => true], 'transfers' => ['requested' => true]],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Express account could not be created', ['error' => $e->getMessage()]);

            throw ValidationException::withMessages(['stripe' => 'Stripe could not set up an account: '.$e->getMessage()]);
        }

        return (string) $account->id;
    }

    /**
     * Stripe's own page where a lodge officer gives their identity and bank details.
     */
    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        try {
            return (string) $this->client()->accountLinks->create(['account' => $accountId, 'refresh_url' => $refreshUrl, 'return_url' => $returnUrl, 'type' => 'account_onboarding'])->url;
        } catch (ApiErrorException $e) {
            Log::warning('Stripe onboarding link failed', ['account' => $accountId, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['stripe' => 'Stripe could not start the setup. Please try again shortly.']);
        }
    }

    /**
     * @return array{country: ?string, details_submitted: bool, charges_enabled: bool, payouts_enabled: bool, requirements: list<string>}
     */
    public function status(string $accountId): array
    {
        try {
            $account = $this->client()->accounts->retrieve($accountId);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe account could not be read', ['account' => $accountId, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['stripe' => 'Stripe could not confirm the account. Please try again shortly.']);
        }

        return self::statusFrom($account);
    }

    /**
     * @return array{country: ?string, details_submitted: bool, charges_enabled: bool, payouts_enabled: bool, requirements: list<string>}
     */
    public static function statusFrom(object $account): array
    {
        return [
            'country' => isset($account->country) ? strtoupper((string) $account->country) : null,
            'details_submitted' => (bool) ($account->details_submitted ?? false),
            'charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
            'requirements' => array_values(array_map('strval', (array) ($account->requirements->currently_due ?? []))),
        ];
    }

    private function client(): StripeClient
    {
        return new StripeClient((string) config('platform_payments.stripe_secret'));
    }

    private function useKey(): void
    {
        Stripe::setApiKey((string) config('platform_payments.stripe_secret'));
    }
}
