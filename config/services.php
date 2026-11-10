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

    'smsmisr' => [
        'username' => env('SMSMISR_USERNAME'),
        'password' => env('SMSMISR_PASSWORD'),
        'sender' => env('SMSMISR_SENDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WAHA (WhatsApp HTTP API)
    |--------------------------------------------------------------------------
    |
    | Credentials for the self-hosted WAHA instance that delivers the
    | WhatsApp notifications. Read-only from the Filament dashboard: the
    | "إعدادات واتساب" page surfaces these values and can test them, but
    | changing them is a deploy-time edit of the environment file.
    |
    */

    'waha' => [
        'url' => env('WAHA_URL', 'http://localhost:3000'),
        'api_key' => env('WAHA_API_KEY'),
        'session' => env('WAHA_SESSION', 'default'),

        // Seconds to wait for the WAHA instance before giving up.
        'timeout' => (int) env('WAHA_TIMEOUT', 15),

        // How many times to retry a transport-level failure (not an API error).
        'retries' => (int) env('WAHA_RETRIES', 2),

        // Country code assumed when a stored phone number has no international prefix.
        'default_country_code' => env('WAHA_DEFAULT_COUNTRY_CODE', '20'),
    ],

];
