<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerEntry extends Model
{
    protected $table = 'winner_entries';

    protected $fillable = [
        'msisdn',
        'box_type',
        'prize',
        'prize_type',
        'amount',
        'awarded_at',
        'box_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'awarded_at' => 'datetime'
    ];

    public $timestamps = false;

    public function winner()
    {
        return $this->hasOne(Winner::class, 'source_id')->where('source_table', 'winner_entries');
    }
}
