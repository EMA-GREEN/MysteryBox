<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WinnerWalletDisbursementNotification extends Model
{
    use HasFactory;

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
        'amount' => 'decimal:2',
        'publish_status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
} 