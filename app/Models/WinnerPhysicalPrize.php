<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WinnerPhysicalPrize extends Model
{
    protected $fillable = [
        'msisdn',
        'winner_id',
        'box_id',
        'box_type',
        'device_type',
        'specific_item',
        'status',
        'tracking_number',
        'shipping_address',
        'delivery_date'
    ];

    protected $casts = [
        'delivery_date' => 'date'
    ];

    public function winner()
    {
        return $this->belongsTo(Winner::class);
    }
}
