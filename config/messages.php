<?php

return [
    'payment' => [
        'success' => [
            'initiate' => 'Charge request sent',
            'callback' => 'Payment successfully',
            'failed' => 'Payment failed. Try again later.'
        ],
        'error' => [
            'invalid_box' => 'Invalid box type.',
            'not_found' => 'Payment not found',
            'invalid_callback' => 'Invalid callback data',
            'missing_fields' => 'Missing required fields: msisdn, request_id, or status'
        ]
    ],
    'mystery_box' => [
        'success' => [
            'open_request' => 'Open Box request under process',
            'opened' => 'Mystery Box opened successfully',
            'win' => "Congratulations! You've won {prize} from your Mystery Box!",
            'awarded' => 'Data received successfully'
        ],
        'error' => [
            'invalid' => 'Invalid or already opened mystery box.',
            'already_opened' => 'This box has already been opened.',
            'in_progress' => 'Box opening request already in progress.',
            'no_request' => 'No open box request found.',
            'invalid_type' => 'Invalid box type'
        ]
    ],
    'otp' => [
        'sms_message' => 'Your OTP code is: {otp_code}. Valid for {expiry_minutes} minutes. Do not share this code with anyone.',
        'success' => [
            'sent' => 'OTP sent successfully',
            'verified' => 'OTP verified successfully',
            'sms_failed' => 'OTP generated but SMS delivery failed'
        ],
        'error' => [
            'invalid' => 'Invalid or expired OTP',
            'rate_limited' => 'OTP already sent. Please wait before requesting a new one.',
            'validation_failed' => 'Validation failed',
            'send_failed' => 'Failed to send OTP',
            'verify_failed' => 'Failed to verify OTP',
            'status_check_failed' => 'Failed to check OTP status'
        ]
    ],
    'sms' => [
        'box_selection_header' => 'Chagua namba ya box. Mfano: 1',
        'box_selection_footer' => 'Tuma namba ya box.',
        'payment_success' => 'Malipo yamefanikiwa. Tafadhali subiri kidogo.',
        'payment_failed' => 'Malipo yameshindwa. Tafadhali jaribu tena.',
        'success_renewal_sms' => env('RENEWAL_SUCCESS_SMS', 'Hongera umezawadiwa nafasi ya kufungua sanduku la Shaba, Tuma neno FUNGUA na unaweza kujishindia zawaidi kemkem.'),
        'low_balance' => 'Salio lako halitoshi. Tafadhali ongeza salio au piga *147*00# chagua Niwezeshe ukope salio na ujaribu tena kuchagua BOX la ushindi.',
        'box_win_loyalty_only' => '{prize}, Fungua sanduka zaidi kwa zawadi zaidi.',
        'box_win_with_other_rewards' => '{prefix} {prize}, Fungua sanduka zaidi kwa zawadi zaidi.',
        'box_win_prefix_airtime_cash' => 'Umeshinda',
        'box_win_prefix_other' => 'Umeingia',
        'box_loss' => 'Asante kwa kushiriki! Jaribu tena baadae.',
        'mystery_box_award' => 'Hongera! Yas imekupa nafasi ya kufungua Mystery Box ya %s. Tafadhali tembelea Mix App kufungua Box yako',
        'mystery_box_award_mixx_payment' => 'Hongera! Yas imekupa nafasi ya kufungua Mystery Box ya %s. Tafadhali tuma neno WIN kufungua Box yako',
        'welcome_message' => env('WELCOME_MESSAGE', 'Karibu kwenye Box la Ushindi! Shinda pesa hadi sehemu ya sh 50,000,000, Salio la mpaka sh 50,000, simu janja na zawadi KIBAAO. Chagua na lipia sanduku la bahati'),
        'welcome_again_message' => env('WELCOME_AGAIN_MESSAGE', 'Karibu tena mchezoni! Shinda pesa hadi sehemu ya sh 50,000,000, Salio la mpaka sh 50,000, simu janja na zawadi KIBAAO. Chagua na lipia sanduku la bahati'),
        'terms_message' => env('TERMS_MESSAGE', 'Unashauriwa kupitia vigezo na mashart ili kuelewa mchezo kiundani. Bofya hapa kupitia. https://www.boxlaushindi.co.tz/terms'),
        'points_message' => 'Salio lako ni points: {points}',
        'help_message' => 'Jinsi ya kushiriki Mystery Box: Tuma neno BOX ili kuona chaguzi.',
        'invalid_response' => 'Jibu lako si sahihi. Tafadhali jaribu tena.',
        'unsubscription_message' => 'Umefanikiwa kujiondoa kwenye huduma yetu. Asante kwa kutumia huduma zetu.',
        'no_mystery_box_found' => 'Hakuna Box iliopatikana kwa ajili ya kufunguliwa.',
        'invalid_sequence' => "Chaguo sio sahihi, Anza kwa kutuma neno BOX kupata orodha ya masanduku yaliyopo.",
        'invalid_response' => "Chaguo lako sio sahihi. Tuma neno BOX kwenda 15099 upate orodha ya masanduku yaliyopo. Chagua namba ya sanduku la bahati yako ubahatishe zawadi mbali mbali.",
        'reward_formats' => [
            'airtime' => 'salio la Tsh {amount}',
            'cash' => 'pesa taslim Tsh {amount}',
            'jackpot_entry' => 'DROO ya {jackpot}',
            'loyalty_points' => 'umezawadiwa point {points} pekee',
        ],
        'loyalty_points_prefix' => 'pia ',
        'jackpot_periods' => [
            'daily' => 'siku',
            'weekly' => 'wiki',
            'monthly' => 'mwezi',
        ],
        'jackpot_connectors' => [
            'middle' => ', ya ',
            'last' => ' na ya ',
        ],
        
        // Points redemption messages
        'redeem' => [
            'header' => env('REDEEM_POINTS_HEADER', 'Una Points'),
            'open_text' => env('REDEEM_OPEN_TEXT', 'Kufungua'),
            'points_text' => env('REDEEM_POINTS_TEXT', 'ni point'),
            'footer' => env('REDEEM_POINTS_FOOTER', 'Tuma namba ya chaguo kufungua sanduku. Mfano: 1'),
            'points_used_prefix' => env('REDEEM_POINTS_USED_PREFIX', 'Umetumia point zako {redeem_points} na'),
            'low_points' => env('REDEEM_LOW_POINTS_MESSAGE', 'Huna point za kutosha kufungua sanduku, kusanya point kuanzia 100 ili uweze kufungua sanduku. Tuma neno BOX kununua sanduku upate POINT na ubahatishe MAMAILIONI'),
            'invalid_choice' => env('REDEEM_INVALID_CHOICE', 'Chaguo si sahihi. Tuma namba sahihi ya box unayotaka kufungua.'),
            'success' => env('REDEEM_SUCCESS_MESSAGE', 'Box imefunguliwa kwa mafanikio! Tazama zawadi yako.'),
        ],
    ]
];