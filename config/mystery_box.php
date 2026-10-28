<?php

return [
    'publisher' => [
        'batch_size' => env('MYSTERY_BOX_PUBLISHER_BATCH_SIZE', 50),
        'queue_name' => env('MYSTERY_BOX_AWARD_QUEUE', 'mystery-box-award-notify-queue'),
    ],
]; 