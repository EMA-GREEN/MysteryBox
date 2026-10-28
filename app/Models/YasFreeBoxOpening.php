<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YasFreeBoxOpening extends Model
{
    protected $table = 'yas_free_box_openings';

    protected $fillable = [
        'msisdn',
        'source',
        'open_date',
        'open_index',
        'user_open_number',
        'is_winner',
        'prize_amount',
        'prize_type',
        'status',
        'reference_id',
        'request_id',
    ];

    protected $casts = [
        'open_date' => 'date',
        'open_index' => 'integer',
        'user_open_number' => 'integer',
        'is_winner' => 'boolean',
        'prize_amount' => 'decimal:2',
    ];
}
