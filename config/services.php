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

    // Push to the iOS app straight through Apple (an APNs auth key, .p8) and to the Android app through
    // Firebase Cloud Messaging (a service account JSON file). Either one left unset simply sends nothing.
    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'key_path' => env('APNS_KEY_PATH'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'uk.clubmanager.app'),
        'production' => (bool) env('APNS_PRODUCTION', false),
    ],

    'fcm' => [
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

    // Phone and computer notifications from the website (Web Push). Make a key pair once per install with
    // `php artisan app:make-web-push-keys`; the public key is sent to browsers, the private key never is.
    'webpush' => [
        'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
        'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
        'subject' => env('WEB_PUSH_SUBJECT', env('APP_URL')),
    ],

    // OpenStreetMap's Nominatim refuses generic or default User-Agents, so identify the platform (optionally
    // with a contact address) for the Map block's address search.
    'nominatim' => [
        'user_agent' => env('NOMINATIM_USER_AGENT', 'ClubManager/1.0 (+'.env('APP_URL', 'http://localhost').')'),
        // Nominatim allows one request a second; follow-up attempts within a search wait this long.
        'gap_ms' => 1100,
    ],

    // The host a lodge's custom domain must point at (CNAME, or the same A record); each server sets its own.
    'club_domain' => [
        'target' => env('CLUB_DOMAIN_TARGET', 'manager.360fusionhosting.co.uk'),
    ],
];
