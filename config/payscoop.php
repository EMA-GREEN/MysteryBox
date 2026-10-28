<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payscoop API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Payscoop API integration
    | for airtime and wallet disbursements.
    |
    */

    'api_key' => env('PAYSCOOP_API_KEY', 'CtG9mxHP3izsACIU4f5aCwgtf34QDVLK'),
    'api_secret' => env('PAYSCOOP_API_SECRET', 'oW8jJzI0jxBCyMLLa9jy04cU0HBg68eneVJywjd71RFpuk0I'),
    
    'base_url' => env('PAYSCOOP_BASE_URL', 'https://api.payscoop.com'),
    
    'endpoints' => [
        'airtime' => '/api/v2/airtime/requests/create',
        'wallet' => '/api/v2/disbursement/create',
    ],
    
    'currency' => env('PAYSCOOP_CURRENCY', 'TZS'),
    
    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    */
    'timeout' => env('PAYSCOOP_TIMEOUT', 30),
    'retry_attempts' => env('PAYSCOOP_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('PAYSCOOP_RETRY_DELAY', 5),
];
