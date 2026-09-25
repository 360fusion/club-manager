<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'price_basic' => env('STRIPE_PRICE_BASIC', 'price_stripe_basic_demo'),
        'price_pro' => env('STRIPE_PRICE_PRO', 'price_stripe_pro_demo'),
    ],

    'paddle' => [
        'price_basic' => env('PADDLE_PRICE_BASIC', 'pri_paddle_basic_demo'),
        'price_pro' => env('PADDLE_PRICE_PRO', 'pri_paddle_pro_demo'),
    ],

    // OpenStreetMap's Nominatim refuses generic or default User-Agents, so identify the platform (optionally
    // with a contact address) for the Map block's address search.
    'nominatim' => [
        'user_agent' => env('NOMINATIM_USER_AGENT', 'ClubManager/1.0 (+'.env('APP_URL', 'http://localhost').')'),
        // Nominatim allows one request a second; follow-up attempts within a search wait this long.
        'gap_ms' => 1100,
    ],
];
