<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fraud Check Enabled
    |--------------------------------------------------------------------------
    |
    | Global switch to enable or disable courier customer fraud checks.
    |
    */
    'enabled' => env('PATHAO_FRAUD_CHECK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Pathao Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and URLs for Pathao. Priority is given to database settings
    | configured under DeliveryPartner (slug: pathao), with fallback to these env values.
    |
    */
    'pathao' => [
        'base_url'           => env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'),
        'merchant_panel_url' => env('PATHAO_MERCHANT_PANEL_URL', 'https://merchant.pathao.com'),
        'client_id'          => env('PATHAO_CLIENT_ID'),
        'client_secret'      => env('PATHAO_CLIENT_SECRET'),
        'username'           => env('PATHAO_USERNAME'),
        'password'           => env('PATHAO_PASSWORD'),
        'merchant_id'        => env('PATHAO_MERCHANT_ID'),
        'sandbox'            => env('PATHAO_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurable Risk Assessment Rules
    |--------------------------------------------------------------------------
    |
    | Thresholds used to evaluate the customer risk level based on delivery history.
    |
    */
    'rules' => [
        'high_cancel_rate'          => (float) env('FRAUD_CHECK_HIGH_CANCEL_RATE', 50.0),
        'medium_cancel_rate'        => (float) env('FRAUD_CHECK_MEDIUM_CANCEL_RATE', 25.0),
        'min_orders_for_evaluation' => (int) env('FRAUD_CHECK_MIN_ORDERS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Seconds)
    |--------------------------------------------------------------------------
    |
    | Results are cached by normalized phone number for 30 minutes (1800 seconds).
    |
    */
    'cache_ttl' => (int) env('FRAUD_CHECK_CACHE_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum wait time for Pathao API requests in seconds.
    |
    */
    'timeout' => (int) env('PATHAO_TIMEOUT', 15),
];
