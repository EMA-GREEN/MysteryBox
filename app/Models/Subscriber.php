<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $table = 'subscribers';
    protected $primaryKey = 'msisdn';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'msisdn',
        'customer_type',
        'boom_credit',
        'last_int_billing',
        'total_int_billed_amount',
        'last_renewal_billing',
        'total_renewal_billed_amount',
        'credit',
        'last_interaction',
        'activity_status',
        'dnd_status',
        'sub_channel',
        'record_date',
        'loyalty_points',
        'points_used',
        'txns_count',
        'today_txns_amount',
        'total_txns_amount',
        'total_txns_fees',
        'last_awarded_box',
        'last_awarded_box_date'
    ];

    protected $casts = [
        'msisdn' => 'integer',
        'boom_credit' => 'integer',
        'last_int_billing' => 'datetime',
        'total_int_billed_amount' => 'integer',
        'last_renewal_billing' => 'datetime',
        'total_renewal_billed_amount' => 'integer',
        'credit' => 'integer',
        'last_interaction' => 'datetime',
        'record_date' => 'datetime',
        'loyalty_points' => 'integer',
        'points_used' => 'integer',
        'today_txns_amount' => 'decimal:2',
        'total_txns_fees' => 'decimal:2',
        'last_awarded_box_date' => 'date'
    ];

    public static function findOrCreate($msisdn, $data = [])
    {
        $subscriber = self::where('msisdn', $msisdn)->first();
        
        if (!$subscriber) {
            $subscriber = self::create([
                'msisdn' => $msisdn,
                'customer_type' => $data['customer_type'] ?? null,
                'boom_credit' => $data['boom_credit'] ?? 0,
                'last_int_billing' => $data['last_int_billing'] ?? now(),
                'total_int_billed_amount' => $data['total_int_billed_amount'] ?? 0,
                'last_renewal_billing' => $data['last_renewal_billing'] ?? null,
                'total_renewal_billed_amount' => $data['total_renewal_billed_amount'] ?? 0,
                'credit' => $data['credit'] ?? 0,
                'last_interaction' => $data['last_interaction'] ?? now(),
                'activity_status' => $data['activity_status'] ?? 'ACTIVE',
                'dnd_status' => $data['dnd_status'] ?? 'OFF',
                'sub_channel' => $data['sub_channel'] ?? 'SMS',
                'record_date' => now(),
                'loyalty_points' => $data['loyalty_points'] ?? 0,
                'txns_count' => $data['txns_count'] ?? 0,
                'today_txns_amount' => $data['today_txns_amount'] ?? 0,
                'total_txns_amount' => $data['total_txns_amount'] ?? 0,
                'total_txns_fees' => $data['total_txns_fees'] ?? 0,
                'last_awarded_box' => $data['last_awarded_box'] ?? null,
                'last_awarded_box_date' => $data['last_awarded_box_date'] ?? null
            ]);
        } else {
            $subscriber->update([
                'last_interaction' => now(),
                'customer_type' => $data['customer_type'] ?? $subscriber->customer_type,
                'boom_credit' => $data['boom_credit'] ?? $subscriber->boom_credit,
                'credit' => $data['credit'] ?? $subscriber->credit,
                'activity_status' => $data['activity_status'] ?? $subscriber->activity_status,
                'dnd_status' => $data['dnd_status'] ?? $subscriber->dnd_status,
                'sub_channel' => $data['sub_channel'] ?? $subscriber->sub_channel,
                'loyalty_points' => $data['loyalty_points'] ?? $subscriber->loyalty_points,
                'txns_count' => $data['txns_count'] ?? $subscriber->txns_count,
                'today_txns_amount' => $data['today_txns_amount'] ?? $subscriber->today_txns_amount,
                'total_txns_amount' => $data['total_txns_amount'] ?? $subscriber->total_txns_amount,
                'total_txns_fees' => $data['total_txns_fees'] ?? $subscriber->total_txns_fees,
                'last_awarded_box' => $data['last_awarded_box'] ?? $subscriber->last_awarded_box,
                'last_awarded_box_date' => $data['last_awarded_box_date'] ?? $subscriber->last_awarded_box_date
            ]);
        }

        return $subscriber;
    }

    public function updateBillingInfo($amount)
    {
        $this->update([
            'last_int_billing' => now(),
            'total_int_billed_amount' => $this->total_int_billed_amount + $amount
        ]);
    }

    public function incrementLoyaltyPoints($points)
    {
        $this->increment('loyalty_points', $points);
        return $this;
    }

    public function useLoyaltyPoints($points)
    {
        if ($this->loyalty_points >= $points) {
            $this->decrement('loyalty_points', $points);
            $this->increment('points_used', $points);
            return true;
        }
        return false;
    }

    public function getTotalPointsEarned()
    {
        return $this->loyalty_points + $this->points_used;
    }
}