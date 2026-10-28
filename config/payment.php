<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for payment processing and historical data retrieval
    |
    */

    // Maximum number of days to check for historical payment tables when using "all"
    'max_historical_days' => env('PAYMENT_MAX_HISTORICAL_DAYS', 90),

    // Whether to stop searching for historical tables if no data is found for consecutive days
    'stop_on_empty_consecutive_days' => env('PAYMENT_STOP_ON_EMPTY_DAYS', 10),

    // Table naming pattern for historical payment tables
    'historical_table_pattern' => 'payments_%s', // %s will be replaced with Ymd format

    // Current payment table name
    'current_table' => 'payments',

    /*
    |--------------------------------------------------------------------------
    | Payment Callback Settings
    |--------------------------------------------------------------------------
    |
    | Here you can configure the settings for payment callbacks
    |
    */

    // API key for payment callbacks
    'callback_api_key' => env('PAYMENT_CALLBACK_API_KEY', 'your-secure-api-key-here'),

    // List of allowed IPs for payment callbacks
    'allowed_ips' => [
        // Add the IPs of your payment service here
        // Example: '192.168.1.1',
    ],
]; 