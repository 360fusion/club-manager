<?php

namespace Launchpad\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Launchpad\Models\WebhookDelivery;

/**
 * Sends one delivery: a JSON POST of {id, event, created_at, data}, signed (see Webhooks::sign), 5 seconds to answer, redirects not followed.
 * A 2xx answer is delivered. Anything else is tried again later with a longer wait each time (the same as the sync outbox's), until
 * config launchpad.webhooks.max_attempts, then it is marked failed. Each try is recorded on the delivery; the queue's own retries are not used.
 * The queue must be a real one (not `sync`), or the waits are skipped.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== 'pending') {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint->active) {
            $delivery->update(['status' => 'failed', 'last_error' => 'The endpoint was switched off.', 'next_attempt_at' => null]);

            return;
        }

        $body = json_encode([
            'id' => $delivery->uuid,
            'event' => $delivery->event,
            'created_at' => $delivery->created_at->toIso8601String(),
            'data' => $delivery->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $code = null;

        try {
            $target = Webhooks::guard($endpoint->url);

            $request = Http::timeout(5)->withoutRedirecting()->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Launchpad-Webhooks',
                'X-Launchpad-Event' => $delivery->event,
                'X-Launchpad-Delivery' => $delivery->uuid,
                'X-Launchpad-Signature' => Webhooks::sign($timestamp, $body, $endpoint->secret),
            ]);

            // connect to the address that was just checked, not to whatever the name resolves to a moment later
            if (! filter_var($target['host'], FILTER_VALIDATE_IP)) {
                $ip = $target['ips'][0];
                $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]]]);
            }

            $response = $request->withBody($body, 'application/json')->post($endpoint->url);
            $code = $response->status();
            $error = $response->successful() ? null : 'HTTP '.$code;
        } catch (\Throwable $e) {
            $error = Str::limit($e->getMessage(), 500, '');
        }

        $attempts = $delivery->attempts + 1;

        if ($error === null) {
            $delivery->update(['status' => 'delivered', 'attempts' => $attempts, 'response_code' => $code, 'last_error' => null, 'delivered_at' => now(), 'next_attempt_at' => null]);

            return;
        }

        if ($attempts >= (int) config('launchpad.webhooks.max_attempts', 8)) {
            $delivery->update(['status' => 'failed', 'attempts' => $attempts, 'response_code' => $code, 'last_error' => $error, 'next_attempt_at' => null]);

            return;
        }

        $wait = Webhooks::backoff($attempts);
        $delivery->update(['attempts' => $attempts, 'response_code' => $code, 'last_error' => $error, 'next_attempt_at' => now()->addSeconds($wait)]);
        static::dispatch($delivery->id)->delay($wait);
    }
}
