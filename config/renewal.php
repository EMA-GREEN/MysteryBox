<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Renewal Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for renewal payments and
    | processing limits.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Renewal Amount
    |--------------------------------------------------------------------------
    |
    | The default amount to charge for renewal subscriptions.
    |
    */
    'default_amount' => env('RENEWAL_DEFAULT_AMOUNT', 300),

    /*
    |--------------------------------------------------------------------------
    | TPS (Transactions Per Second) Limits
    |--------------------------------------------------------------------------
    |
    | Configure the maximum number of transactions per second for different
    | renewal processing queues to control system load and prevent overload.
    |
    */
    'tps_limits' => [
        'priority_queue' => env('RENEWAL_PRIORITY_TPS_LIMIT', 40),
        'low_priority_queue' => env('RENEWAL_LOW_PRIORITY_TPS_LIMIT', 150),
        'default_max_tps' => env('RENEWAL_DEFAULT_MAX_TPS', 100),
        // Publisher publish rate (messages per second)
        'priority_publisher' => env('RENEWAL_PRIORITY_PUBLISHER_TPS', 40),
        'low_priority_publisher' => env('RENEWAL_LOW_PRIORITY_PUBLISHER_TPS', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Additional configuration for renewal processing behavior.
    |
    */
    'processing' => [
        'retry_attempts' => env('RENEWAL_RETRY_ATTEMPTS', 3),
        'timeout_seconds' => env('RENEWAL_TIMEOUT_SECONDS', 30),
        'max_retries' => env('RENEWAL_MAX_RETRIES', 40),
        'low_priority_max_retries' => env('RENEWAL_LOW_PRIORITY_MAX_RETRIES', 22),
        // COOLDOWN ROLLBACK: remove these three lines when reverting the cooldown feature in publishers
        // Wait for callbacks before advancing to next retry level (callbacks arrive 1-2 sec per request)
        'advancement_cooldown_seconds' => env('RENEWAL_ADVANCEMENT_COOLDOWN_SECONDS', 300),
        'advancement_check_interval' => env('RENEWAL_ADVANCEMENT_CHECK_INTERVAL', 30),
        // Ignore small remainingCount (1-10) - likely new MSISDNs entering, not callback failures; don't block advancement
        'advancement_pending_threshold' => env('RENEWAL_ADVANCEMENT_PENDING_THRESHOLD', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Files Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tracking files used by renewal publishers and reload
    | commands to maintain state and retry information.
    |
    */
    'tracking_files' => [
        'priority_publisher' => [
            'retry_tracking' => env('RENEWAL_PRIORITY_RETRY_FILE', 'storage/logs/renewal_priority_retry_tracking.json'),
            'last_date' => env('RENEWAL_PRIORITY_DATE_FILE', 'storage/logs/renewal_priority_last_date.json'),
        ],
        'low_priority_publisher' => [
            'retry_tracking' => env('RENEWAL_LOW_PRIORITY_RETRY_FILE', 'storage/logs/renewal_low_priority_retry_tracking.json'),
            'last_date' => env('RENEWAL_LOW_PRIORITY_DATE_FILE', 'storage/logs/renewal_low_priority_last_date.json'),
        ],
        'reload_command' => [
            'tracking' => env('RENEWAL_RELOAD_TRACKING_FILE', 'storage/logs/renewal_reload_tracking.json'),
        ],
    ],
];