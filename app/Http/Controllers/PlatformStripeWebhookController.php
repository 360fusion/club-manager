<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe telling the platform that a club's subscription or invoice changed (not a lodge's own card payments: those go to
 * StripeWebhookController). Cashier does the work; this adds what it leaves out: a refusal when no signing secret is set
 * (Cashier skips the signature check then) and a log of event ids so a message that arrives twice is acted on once.
 */
class PlatformStripeWebhookController extends WebhookController
{
    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            abort_if(blank(config('cashier.webhook.secret')), 403, 'No webhook signing secret is set.');

            return $next($request);
        });
        $this->middleware(VerifyWebhookSignature::class);
    }

    public function handleWebhook(Request $request): Response
    {
        $payload = $request->json()->all();
        $customer = $payload['data']['object']['customer'] ?? null;

        $first = DB::table('stripe_events')->insertOrIgnore([
            'stripe_id' => $payload['id'],
            'type' => $payload['type'],
            'customer_id' => is_string($customer) ? $customer : null,
            'received_at' => now(),
        ]);

        if (! $first) {
            return new Response('Webhook already handled', 200);
        }

        try {
            return parent::handleWebhook($request);
        } catch (Throwable $e) {
            // Forget it so Stripe's retry is handled instead of being taken for a repeat.
            DB::table('stripe_events')->where('stripe_id', $payload['id'])->delete();

            throw $e;
        }
    }
}
