<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform-collected card payments (Stripe Connect)
    |--------------------------------------------------------------------------
    |
    | Lodges can connect a Stripe account (an existing one, or a new one Stripe
    | creates for them) instead of pasting their own keys. Payments then go to the
    | lodge's account and the platform's commission is taken from each one. Off by
    | default: switch on only once Stripe Connect is enabled on the platform's
    | Stripe account and the terms with lodges are agreed.
    |
    */

    'enabled' => (bool) env('EVENTS_PLATFORM_PAYMENTS', false),

    // The platform's own Stripe secret key and the Connect settings from its Stripe dashboard.
    'stripe_secret' => env('PLATFORM_STRIPE_SECRET', env('STRIPE_SECRET')),
    'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
    'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),

    /*
    | The commission on each card payment: a percentage plus a fixed amount (in the
    | lodge's currency), never below the minimum or above the cap (0 = no limit).
    | A lodge can be given its own rate by the superadmin.
    */
    'commission_percent' => (float) env('PLATFORM_COMMISSION_PERCENT', 2.0),
    'commission_fixed' => (float) env('PLATFORM_COMMISSION_FIXED', 0.0),
    'commission_min' => (float) env('PLATFORM_COMMISSION_MIN', 0.0),
    'commission_max' => (float) env('PLATFORM_COMMISSION_MAX', 0.0),

    // Where lodges read the terms they accept before taking payments this way.
    'terms_url' => env('PLATFORM_PAYMENT_TERMS_URL'),

];
