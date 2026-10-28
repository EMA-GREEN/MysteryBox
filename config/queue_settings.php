<?php

return [
    'wallet_disbursement' => [
        'publisher' => [
            'batch_size' => env('WALLET_DISBURSEMENT_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('WALLET_DISBURSEMENT_QUEUE', 'mystery-box-wallet-disbursements-queue'),
            'lock_name' => env('WALLET_DISBURSEMENT_PUBLISHER_LOCK', 'wallet-disbursement-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('WALLET_DISBURSEMENT_QUEUE', 'mystery-box-wallet-disbursements-queue'),
            'count' => env('WALLET_DISBURSEMENT_CONSUMER_COUNT', 3),
            'prefetch_count' => env('WALLET_DISBURSEMENT_PREFETCH_COUNT', 1),
        ],
        'tax_percent' => env('WALLET_DISBURSEMENT_TAX_PERCENT', 15),
    ],
    'airtime_disbursement' => [
        'publisher' => [
            'batch_size' => env('AIRTIME_DISBURSEMENT_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('AIRTIME_DISBURSEMENT_QUEUE', 'mystery-box-airtime-disbursements-queue'),
            'lock_name' => env('AIRTIME_DISBURSEMENT_PUBLISHER_LOCK', 'airtime-disbursement-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('AIRTIME_DISBURSEMENT_QUEUE', 'mystery-box-airtime-disbursements-queue'),
            'count' => env('AIRTIME_DISBURSEMENT_CONSUMER_COUNT', 3),
            'prefetch_count' => env('AIRTIME_DISBURSEMENT_PREFETCH_COUNT', 1),
        ],
    ],
    'app_notification' => [
        'publisher' => [
            'batch_size' => env('APP_NOTIFICATION_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('APP_NOTIFICATION_QUEUE', 'mystery-box-app-notifications-queue'),
            'lock_name' => env('APP_NOTIFICATION_PUBLISHER_LOCK', 'app-notification-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('APP_NOTIFICATION_QUEUE', 'mystery-box-app-notifications-queue'),
            'count' => env('APP_NOTIFICATION_CONSUMER_COUNT', 3),
            'prefetch_count' => env('APP_NOTIFICATION_PREFETCH_COUNT', 1),
        ],
    ],
    'sms_notification' => [
        'publisher' => [
            'batch_size' => env('SMS_NOTIFICATION_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('SMS_NOTIFICATION_QUEUE', 'mystery-box-sms-notifications-queue'),
            'lock_name' => env('SMS_NOTIFICATION_PUBLISHER_LOCK', 'sms-notification-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('SMS_NOTIFICATION_QUEUE', 'mystery-box-sms-notifications-queue'),
            'count' => env('SMS_NOTIFICATION_CONSUMER_COUNT', 3),
            'prefetch_count' => env('SMS_NOTIFICATION_PREFETCH_COUNT', 1),
        ],
    ],
    'payment_notification' => [
        'publisher' => [
            'batch_size' => env('PAYMENT_NOTIFICATION_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('PAYMENT_NOTIFICATION_QUEUE', 'mystery-box-payment-notifications-queue'),
            'lock_name' => env('PAYMENT_NOTIFICATION_PUBLISHER_LOCK', 'payment-notification-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('PAYMENT_NOTIFICATION_QUEUE', 'mystery-box-payment-notifications-queue'),
            'count' => env('PAYMENT_NOTIFICATION_CONSUMER_COUNT', 3),
            'prefetch_count' => env('PAYMENT_NOTIFICATION_PREFETCH_COUNT', 1),
        ],
    ],
    'payment_callback' => [
        'publisher' => [
            'batch_size' => env('PAYMENT_CALLBACK_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('PAYMENT_CALLBACK_QUEUE', 'mystery-box-payment-callbacks-queue'),
            'lock_name' => env('PAYMENT_CALLBACK_PUBLISHER_LOCK', 'payment-callback-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('PAYMENT_CALLBACK_QUEUE', 'mystery-box-payment-callbacks-queue'),
            'count' => env('PAYMENT_CALLBACK_CONSUMER_COUNT', 3),
            'prefetch_count' => env('PAYMENT_CALLBACK_PREFETCH_COUNT', 1),
        ],
    ],
    'mystery_box_award' => [
        'publisher' => [
            'batch_size' => env('MYSTERY_BOX_AWARD_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('MYSTERY_BOX_AWARD_QUEUE', 'mystery-box-award-notify-queue'),
            'lock_name' => env('MYSTERY_BOX_AWARD_PUBLISHER_LOCK', 'mystery-box-award-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('MYSTERY_BOX_AWARD_QUEUE', 'mystery-box-award-notify-queue'),
            'count' => env('MYSTERY_BOX_AWARD_CONSUMER_COUNT', 3),
            'prefetch_count' => env('MYSTERY_BOX_AWARD_PREFETCH_COUNT', 1),
        ],
    ],
    'mobile_money_transaction_notification' => [
        'publisher' => [
            'batch_size' => 10,
            'queue_name' => 'mystery-box-mobile-money-transaction-notification-queue',
            'lock_name' => 'bill-payment-notification-publisher-lock',
        ],
        'consumer' => [
            'queue_name' => 'mystery-box-mobile-money-transaction-notification-queue',
            'count' => 20,
            'prefetch_count' => 1,
        ],
    ],
    'airtime_disbursement_callback' => [
        'publisher' => [
            'batch_size' => env('AIRTIME_DISBURSEMENT_CALLBACK_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('AIRTIME_DISBURSEMENT_CALLBACK_QUEUE', 'mystery-box-airtime-disbursement-callbacks-queue'),
            'lock_name' => env('AIRTIME_DISBURSEMENT_CALLBACK_PUBLISHER_LOCK', 'airtime-disbursement-callback-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('AIRTIME_DISBURSEMENT_CALLBACK_QUEUE', 'mystery-box-airtime-disbursement-callbacks-queue'),
            'count' => env('AIRTIME_DISBURSEMENT_CALLBACK_CONSUMER_COUNT', 3),
            'prefetch_count' => env('AIRTIME_DISBURSEMENT_CALLBACK_PREFETCH_COUNT', 1),
        ],
    ],
    'wallet_disbursement_callback' => [
        'publisher' => [
            'batch_size' => env('WALLET_DISBURSEMENT_CALLBACK_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('WALLET_DISBURSEMENT_CALLBACK_QUEUE', 'mystery-box-wallet-disbursement-callbacks-queue'),
            'lock_name' => env('WALLET_DISBURSEMENT_CALLBACK_PUBLISHER_LOCK', 'wallet-disbursement-callback-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('WALLET_DISBURSEMENT_CALLBACK_QUEUE', 'mystery-box-wallet-disbursement-callbacks-queue'),
            'count' => env('WALLET_DISBURSEMENT_CALLBACK_CONSUMER_COUNT', 3),
            'prefetch_count' => env('WALLET_DISBURSEMENT_CALLBACK_PREFETCH_COUNT', 1),
        ],
    ],
    'renewal_payment_priority' => [
        'exchange' => env('RENEWAL_PAYMENT_PRIORITY_EXCHANGE', 'mystery-box-renewal-payment-priority-exchange'),
        'routing_key' => env('RENEWAL_PAYMENT_PRIORITY_ROUTING_KEY', 'renewal.payment.priority.key'),
        'publisher' => [
            'batch_size' => env('RENEWAL_PAYMENT_PRIORITY_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('RENEWAL_PAYMENT_PRIORITY_QUEUE', 'mystery-box-renewal-payment-priority-queue'),
            'lock_name' => env('RENEWAL_PAYMENT_PRIORITY_PUBLISHER_LOCK', 'renewal-payment-priority-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('RENEWAL_PAYMENT_PRIORITY_QUEUE', 'mystery-box-renewal-payment-priority-queue'),
            'count' => env('RENEWAL_PAYMENT_PRIORITY_CONSUMER_COUNT', 10),
            'prefetch_count' => env('RENEWAL_PAYMENT_PRIORITY_PREFETCH_COUNT', 2),
        ],
    ],
    'renewal_payment_low_priority' => [
        'exchange' => env('RENEWAL_PAYMENT_LOW_PRIORITY_EXCHANGE', 'mystery-box-renewal-payment-low-priority-exchange'),
        'routing_key' => env('RENEWAL_PAYMENT_LOW_PRIORITY_ROUTING_KEY', 'renewal.payment.low.priority.key'),
        'publisher' => [
            'batch_size' => env('RENEWAL_PAYMENT_LOW_PRIORITY_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('RENEWAL_PAYMENT_LOW_PRIORITY_QUEUE', 'mystery-box-renewal-payment-low-priority-queue'),
            'lock_name' => env('RENEWAL_PAYMENT_LOW_PRIORITY_PUBLISHER_LOCK', 'renewal-payment-low-priority-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('RENEWAL_PAYMENT_LOW_PRIORITY_QUEUE', 'mystery-box-renewal-payment-low-priority-queue'),
            'count' => env('RENEWAL_PAYMENT_LOW_PRIORITY_CONSUMER_COUNT', 10),
            'prefetch_count' => env('RENEWAL_PAYMENT_LOW_PRIORITY_PREFETCH_COUNT', 2),
        ],
    ],
    'renewal_payment_callback' => [
        'publisher' => [
            'batch_size' => env('RENEWAL_PAYMENT_CALLBACK_PUBLISHER_BATCH_SIZE', 100),
            'queue_name' => env('RENEWAL_PAYMENT_CALLBACK_QUEUE', 'mystery-box-renewal-payment-callbacks-queue'),
            'lock_name' => env('RENEWAL_PAYMENT_CALLBACK_PUBLISHER_LOCK', 'renewal-payment-callback-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('RENEWAL_PAYMENT_CALLBACK_QUEUE', 'mystery-box-renewal-payment-callbacks-queue'),
            'count' => env('RENEWAL_PAYMENT_CALLBACK_CONSUMER_COUNT', 10),
            'prefetch_count' => env('RENEWAL_PAYMENT_CALLBACK_PREFETCH_COUNT', 1),
        ],
    ],
    'renewal_content_notification' => [
        'publisher' => [
            'batch_size' => env('RENEWAL_CONTENT_NOTIFICATION_PUBLISHER_BATCH_SIZE', 50),
            'queue_name' => env('RENEWAL_CONTENT_NOTIFICATION_QUEUE', 'mystery-box-renewal-content-notifications-queue'),
            'lock_name' => env('RENEWAL_CONTENT_NOTIFICATION_PUBLISHER_LOCK', 'renewal-content-notification-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('RENEWAL_CONTENT_NOTIFICATION_QUEUE', 'mystery-box-renewal-content-notifications-queue'),
            'count' => env('RENEWAL_CONTENT_NOTIFICATION_CONSUMER_COUNT', 5),
            'prefetch_count' => env('RENEWAL_CONTENT_NOTIFICATION_PREFETCH_COUNT', 1),
        ],
    ],
    'mpush_subs_init_notification' => [
        'publisher' => [
            'batch_size' => env('MPUSH_SUBS_INIT_PUBLISHER_BATCH_SIZE', 10),
            'queue_name' => env('MPUSH_SUBS_INIT_QUEUE', 'mystery-box-mpush-subs-init-queue'),
            'lock_name' => env('MPUSH_SUBS_INIT_PUBLISHER_LOCK', 'mpush-subs-init-publisher-lock'),
        ],
        'consumer' => [
            'queue_name' => env('MPUSH_SUBS_INIT_QUEUE', 'mystery-box-mpush-subs-init-queue'),
            'count' => env('MPUSH_SUBS_INIT_CONSUMER_COUNT', 5),
            'prefetch_count' => env('MPUSH_SUBS_INIT_PREFETCH_COUNT', 1),
        ],
    ],
];