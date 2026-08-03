<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // Outbound SMS alerts (e.g. "new online order"). 'log' is a no-op driver
    // that records the message to the log; switch to a real provider once you
    // have credentials and map it in AppServiceProvider's SmsSender binding.
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'from' => env('SMS_FROM'),
        'termii' => [
            'key' => env('TERMII_API_KEY'),
            'endpoint' => env('TERMII_ENDPOINT', 'https://api.ng.termii.com'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    // Paystack online payments (storefront card checkout). Set both keys from
    // your Paystack dashboard to enable online payment; leave blank to fall back
    // to pay-on-delivery only. Test keys for staging, live keys for production.
    'paystack' => [
        'secret' => env('PAYSTACK_SECRET_KEY'),
        'public' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    // AI product-image generation (background removal, recolor, side views,
    // model shots). Set IMAGE_AI_KEY to enable; the 'stub' provider echoes the
    // source image for local testing without an external call.
    'image_ai' => [
        'provider' => env('IMAGE_AI_PROVIDER', 'gemini'),
        'key' => env('IMAGE_AI_KEY'),
        'model' => env('IMAGE_AI_MODEL', 'gemini-2.5-flash-image'),
        'endpoint' => env('IMAGE_AI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    // Text embeddings that power semantic product search. Defaults to Google
    // Gemini and reuses the same key as the AI image tools. Set EMBEDDINGS_KEY
    // to override, leave blank to disable semantic search (fuzzy still works),
    // or use EMBEDDINGS_PROVIDER=stub for deterministic offline vectors in tests.
    'embeddings' => [
        'provider' => env('EMBEDDINGS_PROVIDER', 'gemini'),
        'key' => env('EMBEDDINGS_KEY', env('IMAGE_AI_KEY')),
        'model' => env('EMBEDDINGS_MODEL', 'gemini-embedding-001'),
        'endpoint' => env('EMBEDDINGS_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        'dims' => (int) env('EMBEDDINGS_DIMS', 768),
    ],

];
