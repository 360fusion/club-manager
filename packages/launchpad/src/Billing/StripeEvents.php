<?php

namespace Launchpad\Billing;

/** The events a Stripe webhook endpoint must listen to for this app to keep working (Cashier's, plus the two it sends mail on). */
final class StripeEvents
{
    public const REQUIRED = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.subscription.trial_will_end',
        'customer.updated',
        'customer.deleted',
        'invoice.payment_action_required',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
    ];
}
