<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yas Payment Service Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for Yas payment service.
    |
    */

    'yas' => [
        'enabled' => env('YAS_ENABLED', true),
        'charging_endpoint' => env('YAS_CHARGING_ENDPOINT', 'http://10.222.10.232:8086/api/public/PAY/Payment'),
        'charging_endpoint_renewal' => env('YAS_CHARGING_ENDPOINT', 'http://10.222.10.232:8086/api/public/PAY_AAT/PAY_AAT'),
        'token_endpoint' => env('YAS_TOKEN_ENDPOINT', 'http://10.222.10.232:8086/api/auth/login'),
        'username' => env('YAS_USERNAME', 'GreenTelecom'),
        'password' => env('YAS_PASSWORD', 'Tigo@1234'),
        'hashed_password' => md5('86' . 'Tigo@1234' . date('YmdHis')),
        'source_address' => env('SOURCE_ADDRESS', '188.64.189.38'),
        'channel' => env('YAS_CHANNEL', '10'),
        'offer_code' => env('YAS_OFFER_CODE', '100000008600289'),
        'cp_id' => env('YAS_CP_ID', '86'),
        'type_of_service' => env('YAS_TYPE_OF_SERVICE', 'InteractiveService'),
        'language' => env('YAS_LANGUAGE', '1'),
        'activation_endpoint' => env('YAS_ACTIVATION_ENDPOINT', 'http://10.222.10.232:8086/api/public/ACT/Activate'),
        'mpush_offer_code' => env('YAS_MPUSH_OFFER_CODE', '017008600010'),
        'mpush_source_address' => env('YAS_MPUSH_SOURCE_ADDRESS', '188.64.189.38'),
    ],

    // Helper function to get Yas configuration
    'get_mno_config' => function() {
        return config('mno_services.yas', []);
    },
]; 


// https://api.yas.com/charge