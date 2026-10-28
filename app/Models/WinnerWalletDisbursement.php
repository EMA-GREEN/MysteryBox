<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WinnerWalletDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'msisdn',
        'amount',
        'payout_amount',
        'tax_percent',
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
        'amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'request_sent_time' => 'datetime',
        'ack_received_time' => 'datetime',
        'callback_received_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
} 