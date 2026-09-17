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

    'wag' => [
        'url' => env('WAG_URL', 'https://waghub.mekayastudio.com'),
        'token' => env('WAG_TOKEN'),
        'verify_ssl' => env('WAG_VERIFY_SSL', false),
        'public_url' => env('WAG_PUBLIC_URL', env('APP_URL')),
        'ar_phone' => env('AR_DEFAULT_PHONE', '081224290502'),
    ],

    'openai_compatible' => [
        'base_url' => env('OPENAI_COMPATIBLE_BASE_URL', 'https://router.rizqis.com/v1'),
        'api_key' => env('OPENAI_COMPATIBLE_API_KEY', 'sk-3948f4654c3abea8-q0276y-980d240c'),
        'model' => env('OPENAI_COMPATIBLE_MODEL', 'ag/gemini-3-flash'),
    ],

];
