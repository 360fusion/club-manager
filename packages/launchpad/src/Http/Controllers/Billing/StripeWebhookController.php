<?php

namespace Launchpad\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cashier's webhook, but refusing everything while no signing secret is set. Cashier alone accepts unsigned calls then, and anyone could tell
 * the app a subscription had been paid.
 */
class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request): Response
    {
        abort_if(blank(config('cashier.webhook.secret')), 403, 'The Stripe webhook secret is not set.');

        return parent::handleWebhook($request);
    }
}
