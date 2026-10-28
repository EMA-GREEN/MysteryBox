<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the rate limiting configuration for various API endpoints.
    | All limits are per minute unless otherwise specified.
    |
    */

    'mobile_money_notifications' => [
        'requests_per_second' => env('MOBILE_MONEY_RATE_LIMIT', 150),
        'window_seconds' => 1,
        'key_prefix' => 'mobile_money_notifications',
        'throttle_message' => 'Too many mobile money transaction notifications. Please try again later.',
    ],

    'auth' => [
        'login_attempts_per_minute' => env('AUTH_RATE_LIMIT', 5),
        'otp_attempts_per_minute' => env('OTP_RATE_LIMIT', 3),
    ],

    'general_api' => [
        'requests_per_minute' => env('GENERAL_API_RATE_LIMIT', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Storage
    |--------------------------------------------------------------------------
    |
    | The default cache store to use for rate limiting. This should be a fast
    | cache store like Redis for production environments.
    |
    */
    'cache_store' => env('RATE_LIMIT_CACHE_STORE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Headers
    |--------------------------------------------------------------------------
    |
    | Whether to include rate limiting headers in responses.
    |
    */
    'include_headers' => env('RATE_LIMIT_INCLUDE_HEADERS', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log rate limit violations.
    |
    */
    'log_violations' => env('RATE_LIMIT_LOG_VIOLATIONS', true),
];
