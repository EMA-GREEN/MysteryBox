<?php

return [
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'tigo_renewals'),
    'password' => env('RABBITMQ_PASSWORD', 'manage@CNu8Ut'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
];