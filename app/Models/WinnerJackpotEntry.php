<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerJackpotEntry extends Model
{
    protected $fillable = [
        'msisdn',
        'winner_id',
        'box_id',
        'box_type',
        'jackpot_amount',
        'draw_period',
        'entry_date',
        'draw_date',
        'status',
        'prize_amount'
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'draw_date' => 'date',
        'jackpot_amount' => 'decimal:2',
        'prize_amount' => 'decimal:2'
    ];

    public function winner()
    {
        return $this->belongsTo(Winner::class);
    }
}
