<?php

return [
    'enabled' => env('YAS_FREE_BOX_ENABLED', true),

    'box' => [
        'name' => 'YasFree',
        'display_name' => 'Bure',
        'amount' => 0,
        'channel' => 'APP',
        'show_first' => true,
        'hidden_from_other_sources' => true,
    ],

    // App redirect sources that may offer the Yas free box
    'sources' => [
        'mixx_app' => [
            'label' => 'Mixx App',
            'enabled' => false, // disabled for now; open/status accept yas_app only
        ],
        'yas_app' => [
            'label' => 'Yas App',
            'enabled' => true,
        ],
    ],

    'daily_opens_per_msisdn' => (int) env('YAS_FREE_BOX_DAILY_OPENS', 2),
    'timezone' => 'Africa/Dar_es_Salaam',

    'winning' => [
        'interval' => (int) env('YAS_FREE_BOX_WIN_INTERVAL', 500),
        'max_winners_per_day' => (int) env('YAS_FREE_BOX_MAX_WINNERS', 6),
        'prize_amount' => (float) env('YAS_FREE_BOX_PRIZE_AMOUNT', 5000),
        'prize_type' => env('YAS_FREE_BOX_PRIZE_TYPE', 'cash'),
    ],

    'daily_prize_budget' => (float) env('YAS_FREE_BOX_DAILY_BUDGET', 30000),

    'payout' => [
        'enabled' => true,
        'via' => 'payscoop',
        'reference_prefix' => 'YASF',
    ],
];
