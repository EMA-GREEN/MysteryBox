<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerAirtimeDisbursement extends Model
{
    protected $fillable = [
        'msisdn',
        'amount',
        'reference_id',
        'box_type',
        'box_id',
        'winner_id',
        'request_sent_time',
        'ack_status',
        'ack_message',
        'ack_received_time',
        'callback_status',
        'callback_message',
        'callback_received_time'
    ];

    protected $casts = [
        'request_sent_time' => 'datetime',
        'ack_received_time' => 'datetime',
        'callback_received_time' => 'datetime'
    ];
} 