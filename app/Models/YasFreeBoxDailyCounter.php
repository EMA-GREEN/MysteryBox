<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YasFreeBoxDailyCounter extends Model
{
    protected $table = 'yas_free_box_daily_counters';

    protected $fillable = [
        'open_date',
        'open_count',
        'winners_count',
        'total_prize_amount',
    ];

    protected $casts = [
        'open_date' => 'date',
        'open_count' => 'integer',
        'winners_count' => 'integer',
        'total_prize_amount' => 'decimal:2',
    ];
}
