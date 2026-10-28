<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Box Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all the configuration for mystery boxes including
    | types, amounts, and reward settings.
    |
    */

    // Maximum percentage of box amount that can be awarded as guaranteed monetary rewards
    'monetary_reward_limit' => 30, // 30%

    'types' => [
        '0' => [
            'name' => 'BillPay',
            'display_name' => 'Malipo',
            'amount' => 0, // Hidden box, no purchase amount
            'win_chance' => 0,
            'redeem_points' => 0, // Cannot be redeemed with points
            'hidden' => true, // Hidden throughout the app except in specific consumer
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'loyalty_points', 'min' => 10, 'max' => 100, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => 'cash', 'min' => 500, 'max' => 3000, 'is_enabled' => true],
                        ['type' => 'airtime', 'min' => 500, 'max' => 2000, 'is_enabled' => true],
                    ],
                ],
            ],
        ],
        '1' => [
            'name' => 'Bronze',
            'display_name' => 'Shaba',
            'amount' => 300,
            'win_chance' => 2,
            'redeem_points' => 100, // Points required to open this box for free
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'loyalty_points', 'min' => 5, 'max' => 20, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => 'cash', 'min' => 500, 'max' => 1000, 'is_enabled' => true],
                        ['type' => 'airtime', 'min' => 500, 'max' => 1000, 'is_enabled' => true],
                    ],
                ],
            ],
        ],
        '2' => [
            'name' => 'Silver',
            'display_name' => 'Fedha',
            'amount' => 500,
            'win_chance' => 2,
            'redeem_points' => 200, // Points required to open this box for free
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'loyalty_points', 'min' => 10, 'max' => 30, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => '5g_router', 'is_enabled' => false],
                        ['type' => 'feature_phone', 'is_enabled' => false],
                        ['type' => 'cash', 'min' => 1000, 'max' => 3000, 'is_enabled' => true],
                    ],
                ],
            ],
        ],
        '3' => [
            'name' => 'Gold',
            'display_name' => 'Dhahabu',
            'amount' => 1000,
            'win_chance' => 2,
            'redeem_points' => 300, // Points required to open this box for free
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'loyalty_points', 'min' => 20, 'max' => 50, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => '5g_router', 'is_enabled' => false],
                        ['type' => 'feature_phone', 'is_enabled' => false],
                        ['type' => 'cash', 'min' => 1000, 'max' => 5000, 'is_enabled' => true],
                        ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
                    ],
                ],
            ],
        ],
        '4' => [
            'name' => 'Platinum',
            'display_name' => 'Platinum',
            'amount' => 5000,
            'win_chance' => 1,
            'redeem_points' => 400, // Points required to open this box for free
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'airtime', 'min' => 1000, 'max' => 5000, 'is_enabled' => true],
                    ['type' => 'loyalty_points', 'min' => 30, 'max' => 70, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'weekly', 'amount' => 500000, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'monthly', 'amount' => 1000000, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => 'cash', 'min' => 5000, 'max' => 8000, 'is_enabled' => true],
                        ['type' => '5g_router', 'is_enabled' => false],
                        ['type' => 'feature_phone', 'is_enabled' => false],
                        ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
                    ],
                ],
            ],
        ],
        '5' => [
            'name' => 'Diamond',
            'display_name' => 'Almasi',
            'amount' => 10000,
            'win_chance' => 1,
            'redeem_points' => 500, // Points required to open this box for free
            'rewards' => [
                'guaranteed' => [
                    ['type' => 'airtime', 'min' => 1000, 'max' => 5000, 'is_enabled' => true],
                    ['type' => 'cash', 'min' => 1000, 'max' => 20000, 'is_enabled' => true],
                    ['type' => 'loyalty_points', 'min' => 50, 'max' => 100, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'count' => 5, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'weekly', 'amount' => 500000, 'is_enabled' => true],
                    ['type' => 'jackpot_entry', 'jackpot' => 'monthly', 'amount' => 1000000, 'is_enabled' => true],
                ],
                'possible' => [
                    'is_enabled' => true,
                    'rewards' => [
                        ['type' => '5g_router', 'is_enabled' => false],
                        ['type' => 'feature_phone', 'is_enabled' => true],
                        ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
                    ],
                ],
            ],
        ],
        '6' => [
            'name' => 'YasFree',
            'display_name' => 'Bure',
            'amount' => 0,
            'win_chance' => 0, // unused; Yas free box uses position-based wins
            'redeem_points' => 0,
            'hidden' => true, // SMS Box/Open menu only; app uses the Yas free-box API
            'yas_free' => true,
            'free' => true,
            'sources' => ['yas_app'], // mixx_app deferred; enable later when Mixx redirects go live
            'rewards' => [
                'guaranteed' => [],
                'possible' => [
                    'is_enabled' => false,
                    'rewards' => [],
                ],
            ],
        ],
    ],

    
    // 'types' => [
    //     '0' => [
    //         'name' => 'BillPay',
    //         'display_name' => 'Malipo',
    //         'amount' => 0, // Hidden box, no purchase amount
    //         'win_chance' => 0,
    //         'redeem_points' => 0, // Cannot be redeemed with points
    //         'hidden' => true, // Hidden throughout the app except in specific consumer
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'loyalty_points', 'min' => 10, 'max' => 100, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => 'cash', 'min' => 500, 'max' => 3000, 'is_enabled' => true],
    //                     ['type' => 'airtime', 'min' => 500, 'max' => 2000, 'is_enabled' => true],
    //                 ],
    //             ],
    //         ],
    //     ],
    //     '1' => [
    //         'name' => 'Bronze',
    //         'display_name' => 'Shaba',
    //         'amount' => 300,
    //         'win_chance' => 2,
    //         'redeem_points' => 100, // Points required to open this box for free
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'loyalty_points', 'min' => 5, 'max' => 20, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => 'cash', 'min' => 500, 'max' => 5000, 'is_enabled' => true],
    //                     ['type' => 'airtime', 'min' => 500, 'max' => 2000, 'is_enabled' => true],
    //                 ],
    //             ],
    //         ],
    //     ],
    //     '2' => [
    //         'name' => 'Silver',
    //         'display_name' => 'Fedha',
    //         'amount' => 500,
    //         'win_chance' => 2,
    //         'redeem_points' => 200, // Points required to open this box for free
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'loyalty_points', 'min' => 10, 'max' => 30, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => '5g_router', 'is_enabled' => false],
    //                     ['type' => 'feature_phone', 'is_enabled' => false],
    //                     ['type' => 'cash', 'min' => 1000, 'max' => 10000, 'is_enabled' => true],
    //                 ],
    //             ],
    //         ],
    //     ],
    //     '3' => [
    //         'name' => 'Gold',
    //         'display_name' => 'Dhahabu',
    //         'amount' => 1000,
    //         'win_chance' => 2,
    //         'redeem_points' => 300, // Points required to open this box for free
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'loyalty_points', 'min' => 20, 'max' => 50, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => '5g_router', 'is_enabled' => false],
    //                     ['type' => 'feature_phone', 'is_enabled' => false],
    //                     ['type' => 'cash', 'min' => 5000, 'max' => 10000, 'is_enabled' => true],
    //                     ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
    //                 ],
    //             ],
    //         ],
    //     ],
    //     '4' => [
    //         'name' => 'Platinum',
    //         'display_name' => 'Platinum',
    //         'amount' => 5000,
    //         'win_chance' => 1,
    //         'redeem_points' => 400, // Points required to open this box for free
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'airtime', 'min' => 1000, 'max' => 5000, 'is_enabled' => true],
    //                 ['type' => 'loyalty_points', 'min' => 30, 'max' => 70, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'weekly', 'amount' => 500000, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'monthly', 'amount' => 1000000, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => 'cash', 'min' => 5000, 'max' => 10000, 'is_enabled' => true],
    //                     ['type' => '5g_router', 'is_enabled' => false],
    //                     ['type' => 'feature_phone', 'is_enabled' => false],
    //                     ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
    //                 ],
    //             ],
    //         ],
    //     ],
    //     '5' => [
    //         'name' => 'Diamond',
    //         'display_name' => 'Almasi',
    //         'amount' => 10000,
    //         'win_chance' => 1,
    //         'redeem_points' => 500, // Points required to open this box for free
    //         'rewards' => [
    //             'guaranteed' => [
    //                 ['type' => 'airtime', 'min' => 1000, 'max' => 5000, 'is_enabled' => true],
    //                 ['type' => 'cash', 'min' => 1000, 'max' => 20000, 'is_enabled' => true],
    //                 ['type' => 'loyalty_points', 'min' => 50, 'max' => 100, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'daily', 'amount' => 100000, 'count' => 5, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'weekly', 'amount' => 500000, 'is_enabled' => true],
    //                 ['type' => 'jackpot_entry', 'jackpot' => 'monthly', 'amount' => 1000000, 'is_enabled' => true],
    //             ],
    //             'possible' => [
    //                 'is_enabled' => true,
    //                 'rewards' => [
    //                     ['type' => '5g_router', 'is_enabled' => false],
    //                     ['type' => 'feature_phone', 'is_enabled' => true],
    //                     ['type' => 'tech_gadget', 'gadgets' => ['smartphone', 'tablet', 'smartwatch'], 'is_enabled' => false],
    //                 ],
    //             ],
    //         ],
    //     ],
    // ],

    'box_award_criteria' => [
        'min_amount' => 10000,
        'box' => 'BillPay',
    ],

    // Awarded box expiry settings
    'award_expiry_hours' => env('BOX_AWARD_EXPIRY_HOURS', 24),
    'award_expiry_enabled' => env('BOX_AWARD_EXPIRY_ENABLED', true),

    // Renewal payment settings
    'renewal' => [
        'default_amount' => 300, // Default renewal amount
    ],

];