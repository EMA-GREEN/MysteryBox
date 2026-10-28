<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Winner extends Model
{
    protected $fillable = [
        'msisdn',
        'winner_type',
        'prize_description',
        'prize_value',
        'source_table',
        'source_id',
        'box_id',
        'box_type',
        'draw_period',

        'airtime_disbursement_status',
        'wallet_disbursement_status',
        'airtime_disbursement_reference_id',
        'wallet_disbursement_reference_id',
        'payout_amount',
        'tax_percent',
        'won_at',
        'airtime_disbursed_at',
        'wallet_disbursed_at'
    ];

    protected $casts = [
        'prize_value' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'won_at' => 'datetime',
        'airtime_disbursed_at' => 'datetime',
        'wallet_disbursed_at' => 'datetime'
    ];

    public function winnerEntry()
    {
        return $this->belongsTo(WinnerEntry::class, 'source_id')->where('source_table', 'winner_entries');
    }

    public function jackpotEntry()
    {
        return $this->belongsTo(WinnerJackpotEntry::class, 'source_id')->where('source_table', 'winner_jackpot_entries');
    }
} 