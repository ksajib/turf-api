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

    'mram_sms' => [
        'base_url' => env('MRAM_SMS_BASE_URL'),
        'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('MRAM_SMS_TRUSTED_HOSTS', ''))))),
        'enabled' => filter_var(env('MRAM_SMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'response_diagnostics' => filter_var(env('MRAM_SMS_RESPONSE_DIAGNOSTICS', false), FILTER_VALIDATE_BOOLEAN),
        'mode' => env('MRAM_SMS_MODE', env('APP_ENV') === 'production' ? null : 'test'),
        'credentials' => [
            'test' => [
                'api_key' => env('MRAM_SMS_TEST_API_KEY'),
                'sender_id' => env('MRAM_SMS_TEST_SENDER_ID'),
            ],
            'live' => [
                'api_key' => env('MRAM_SMS_LIVE_API_KEY'),
                'sender_id' => env('MRAM_SMS_LIVE_SENDER_ID'),
            ],
        ],
    ],

    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'mode' => env('SSLCOMMERZ_MODE', 'sandbox'),
        'initiation_url' => env('SSLCOMMERZ_INITIATION_URL', env('SSLCOMMERZ_MODE', 'sandbox') === 'live' ? 'https://securepay.sslcommerz.com/gwprocess/v4/api.php' : 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'),
        'validation_url' => env('SSLCOMMERZ_VALIDATION_URL', env('SSLCOMMERZ_MODE', 'sandbox') === 'live' ? 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php' : 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'callback_url' => env('SSLCOMMERZ_CALLBACK_URL', env('APP_URL', 'http://localhost:8000').'/api/v1/customer/payments/sslcommerz'),
    ],

];
