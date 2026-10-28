<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Mode Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the test mode behavior for the application.
    | When test mode is enabled, certain operations like MysteryBoxAwardNotification
    | creation and subscriber award tracking will only be performed for
    | whitelisted MSISDNs.
    |
    */

    'enabled' => env('TEST_MODE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | MSISDN Whitelist
    |--------------------------------------------------------------------------
    |
    | List of MSISDNs that are allowed to receive full functionality
    | when test mode is enabled. All other MSISDNs will be filtered out
    | from certain operations.
    |
    */

    'msisdn_whitelist' => [
        '255656692469',
        '255712505004',
        '255655807727',
    ],
];