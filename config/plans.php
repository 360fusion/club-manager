<?php

/*
 * What a club gets for its platform subscription. A plan is found from the Stripe price a club is subscribed to, so
 * the price ids come from the environment (STRIPE_PRICE_PRO_MONTHLY and so on) and are never guessed. A club with no
 * subscription is on the plan named in `free`. A limit of null means no limit.
 *
 * No prices or limits are set here on purpose (they are yours to choose, and nothing enforces a limit yet): put the ones you chose in Stripe in the environment and `php artisan billing:doctor`
 * will say what is still missing.
 */
return [
    'free' => 'free',

    'plans' => [
        'free' => [
            'name' => 'Free',
            'prices' => [],
            'limits' => ['members' => null, 'storage_mb' => null],
            'modules' => ['Member Portal'],
        ],
        'pro' => [
            'name' => 'Pro',
            'prices' => [
                'monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'annual' => env('STRIPE_PRICE_PRO_ANNUAL'),
            ],
            'limits' => ['members' => null, 'storage_mb' => null],
            'modules' => ['Accounting ERP', 'Summonses & Meetings', 'Communications Hub', 'Member Portal', 'File Manager'],
        ],
    ],
];
