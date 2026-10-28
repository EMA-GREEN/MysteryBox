<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MysteryBoxAwardNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'msisdn',
        'box_type',
        'request_id',
        'status',
        'publish_status'
    ];

    protected $casts = [
        'publish_status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
