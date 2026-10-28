<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id', 'msisdn', 'amount', 'box_type', 'payment_type', 'status', 'channel',
        'ack_status_code', 'ack_description', 'ack_received_time',
        'callback_status_code', 'callback_description', 'callback_received_time',
        'billing_system', 'box_id',
    ];

    protected $casts = [
        'ack_received_time' => 'datetime',
        'callback_received_time' => 'datetime',
    ];

    // Possible values for payment_type
    const PAYMENT_TYPE_PURCHASE = 'PurchaseBox';
    const PAYMENT_TYPE_OPEN = 'OpenBox';
}

