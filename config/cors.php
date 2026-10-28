<?php

// return [

//     'paths' => ['api/*', 'sanctum/csrf-cookie'],

//     'allowed_methods' => ['*'],

//     'allowed_origins' => ['*'],

//     'allowed_origins_patterns' => [],

//     'allowed_headers' => [
//         'Content-Type',
//         'X-Requested-With',
//         'Authorization',
//         'X-CSRF-TOKEN',
//         'Accept',
//         'Origin',
//         'Access-Control-Request-Method',
//         'Access-Control-Request-Headers'
//     ],

//     'exposed_headers' => [
//         'Access-Control-Allow-Origin',
//         'Access-Control-Allow-Methods',
//         'Access-Control-Allow-Headers'
//     ],

//     'max_age' => 86400,

//     'supports_credentials' => false,

// ];

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
