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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'sumopod' => [
        'api_key' => env('SUMOPOD_API_KEY', ''),
        'base_url' => env('SUMOPOD_BASE_URL', 'https://api-pay.sumopod.com/api/v1'),
        'timeout' => (int) env('SUMOPOD_TIMEOUT', 30),
        'webhook_token' => env('SUMOPOD_WEBHOOK_TOKEN', ''),
    ],

    'pakasir' => [
        'project' => env('PAKASIR_PROJECT', ''),
        'api_key' => env('PAKASIR_API_KEY', ''),
        'base_url' => env('PAKASIR_BASE_URL', 'https://app.pakasir.com/api'),
        'timeout' => (int) env('PAKASIR_TIMEOUT', 30),
    ],

];
