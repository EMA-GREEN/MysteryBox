<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Balance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring wallet/airtime disbursement account balance
    | and sending notifications when balance falls below threshold.
    |
    */

    'threshold' => env('BALANCE_THRESHOLD', 200000), // Balance threshold in TZS

    /*
    |--------------------------------------------------------------------------
    | Notification Recipients
    |--------------------------------------------------------------------------
    |
    | MSISDNs to receive balance alerts when balance falls below threshold.
    |
    */
    'notification_msisdns' => [
        '255656692469',
        '255712505004',
        '255655807727',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Settings for controlling notification frequency and behavior.
    |
    */
    'notification' => [
        'cooldown_minutes' => env('BALANCE_NOTIFICATION_COOLDOWN', 60), // Minimum minutes between notifications
        'message_template' => 'ALERT: Disbursement account balance is TZS {balance}. Balance is below threshold of TZS {threshold}.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Check Settings
    |--------------------------------------------------------------------------
    |
    | Settings for how far back to check for recent disbursement callbacks.
    |
    */
    'check' => [
        'lookback_minutes' => env('BALANCE_CHECK_LOOKBACK', 30), // Check callbacks from last N minutes
        'status' => 'COMPLETED', // Only check callbacks with this status
    ],
];

