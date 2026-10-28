<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Draw Winners Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for selecting top N winners for
    | daily, weekly, and monthly draws.
    |
    */

    'winners' => [
        'daily' => [
            'top_n' => 10, // Top 10 winners for daily draw
            'ranking_criteria' => 'jackpot_amount', // Rank by jackpot_amount (descending)
        ],
        'weekly' => [
            'top_n' => 10, // Top 10 winners for weekly draw
            'ranking_criteria' => 'jackpot_amount', // Rank by jackpot_amount (descending)
        ],
        'monthly' => [
            'top_n' => 10, // Top 10 winners for monthly draw
            'ranking_criteria' => 'jackpot_amount', // Rank by jackpot_amount (descending)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Export Settings
    |--------------------------------------------------------------------------
    */
    'csv' => [
        'directory' => 'winners',
        'filename_format' => '{draw_period}_winners_{date}.csv', // e.g., daily_winners_2025-12-11.csv
        'include_headers' => true,
    ],
];

