<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YasFreeBoxDailyUsage extends Model
{
    protected $table = 'yas_free_box_daily_usage';

    protected $fillable = [
        'msisdn',
        'open_date',
        'opens_used',
    ];

    protected $casts = [
        'open_date' => 'date',
        'opens_used' => 'integer',
    ];
}
