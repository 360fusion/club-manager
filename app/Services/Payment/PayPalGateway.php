<?php

namespace App\Services\Payment;

use App\Models\ClubPaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The only place the app talks to PayPal's API, always with the lodge's own credentials so the money goes
 * straight to the lodge. Tests replace this class, so no test calls PayPal.
 */
class PayPalGateway
{
    /**
     * Start a PayPal checkout for an amount owed. The person approves it on PayPal's own page.
     *
     * @param  array{amount: float, currency: string, reference: string, custom_id: string, description: string, brand: string, return_url: string, cancel_url: string}  $order
     * @return array{id: string, url: string}
     */
    public function createOrder(ClubPaymentMethod $method, array $order): array
    {
        $response = $this->send($method, fn (PendingRequest $http) => $http->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order['reference'],
                'custom_id' => $order['custom_id'],
                'description' => mb_substr($order['description'], 0, 127),
                'amount' => ['currency_code' => strtoupper($order['currency']), 'value' => $this->amount($order['amount'])],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => mb_substr($order['brand'], 0, 127),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $order['return_url'],
                'cancel_url' => $order['cancel_url'],
            ]]],
        ]), 'Online payment is not available right now. Please choose another way to pay or try again shortly.', 'payment');

        $url = collect($response['links'] ?? [])->first(fn ($link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true))['href'] ?? null;

        if (empty($response['id']) || ! $url) {
            throw ValidationException::withMessages(['payment' => 'Online payment is not available right now. Please choose another way to pay or try again shortly.']);
        }

        return ['id' => (string) $response['id'], 'url' => (string) $url];
    }

    /**
     * Take the money for an order the person has approved.
     *
     * @return array{status: string, capture_id: ?string, amount: float, currency: ?string, custom_id: ?string}
     */
    public function captureOrder(ClubPaymentMethod $method, string $orderId): array
    {
        $response = $this->send($method, fn (PendingRequest $http) => $http->withHeaders(['PayPal-Request-Id' => 'capture-'.$orderId])->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture'), 'We could not confirm your PayPal payment. If money left your account, please contact the organiser.', 'payment');

        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? [];

        return [
            'status' => (string) ($capture['status'] ?? $response['status'] ?? ''),
            'capture_id' => isset($capture['id']) ? (string) $capture['id'] : null,
            'amount' => (float) ($capture['amount']['value'] ?? 0),
            'currency' => $capture['amount']['currency_code'] ?? null,
            'custom_id' => $capture['custom_id'] ?? ($response['purchase_units'][0]['custom_id'] ?? null),
        ];
    }

    /**
     * Refund some or all of a PayPal payment.
     */
    public function refund(ClubPaymentMethod $method, string $captureId, float $amount, string $currency): string
    {
        $response = $this->send($method, fn (PendingRequest $http) => $http->post('/v2/payments/captures/'.rawurlencode($captureId).'/refund', [
            'amount' => ['currency_code' => strtoupper($currency), 'value' => $this->amount($amount)],
        ]), 'PayPal could not refund this payment.', 'amount');

        return (string) ($response['id'] ?? '');
    }

    /**
     * Ask PayPal whether a notification really came from it, for this lodge's webhook.
     *
     * @param  array<string, string>  $headers  the PAYPAL-* headers of the request
     */
    public function verifyWebhook(ClubPaymentMethod $method, array $headers, string $body): bool
    {
        $event = json_decode($body, true);
        $webhookId = $method->config['paypal_webhook_id'] ?? null;

        if (! is_array($event) || ! $webhookId) {
            return false;
        }

        try {
            $response = $this->send($method, fn (PendingRequest $http) => $http->post('/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? '',
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]), 'verify', 'payment');
        } catch (ValidationException) {
            return false;
        }

        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }

    private function amount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(ClubPaymentMethod $method, callable $call, string $failure, string $field): array
    {
        $config = $method->config ?? [];
        $base = ($config['paypal_mode'] ?? 'live') === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        try {
            $token = Http::asForm()->timeout(15)->withBasicAuth((string) ($config['paypal_client_id'] ?? ''), (string) ($config['paypal_client_secret'] ?? ''))
                ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json('access_token');

            return $call(Http::baseUrl($base)->timeout(20)->acceptJson()->withToken((string) $token))->throw()->json() ?? [];
        } catch (RequestException|ConnectionException $e) {
            Log::warning('PayPal request failed', ['club' => $method->club_id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages([$field => $failure]);
        }
    }
}
