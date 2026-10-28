<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerAirtimeDisbursementNotification extends Model
{
    protected $fillable = [
        'msisdn',
        'amount',
        'box_type',
        'box_id',
        'winner_id',
        'request_id',
        'publish_status'
    ];

    protected $casts = [
        'publish_status' => 'boolean'
    ];
} 