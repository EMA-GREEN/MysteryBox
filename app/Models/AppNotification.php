<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = [
        'msisdn',
        'action',
        'publish_status',
        'notif_id',
        'box_type',
        'box_id'
    ];

    const ACTION_PURCHASE = 'PURCHASE';
    const ACTION_OPEN = 'OPEN';
} 