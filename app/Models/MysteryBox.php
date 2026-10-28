<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MysteryBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'msisdn',
        'box_type',
        'request_id',
        'amount',
        'status',
        'result',
        'prize',
        'source'
    ];

    protected $attributes = [
        'source' => 'purchase'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_OPENED = 'opened';
    const SOURCE_PURCHASE = 'purchase';
    const SOURCE_MIXX_PAYMENT = 'mixx_payment';
    const SOURCE_RENEWAL = 'renewal';
    const SOURCE_POINT_REDEEM = 'point_redeem';

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Check if this mystery box was awarded due to renewal
     */
    public function isRenewalAward()
    {
        return $this->source === self::SOURCE_RENEWAL || str_starts_with($this->request_id, 'MYBXR');
    }

    /**
     * Check if this mystery box was awarded due to Mixx bill payment
     */
    public function isMixxPaymentAward()
    {
        return $this->source === self::SOURCE_MIXX_PAYMENT || str_starts_with($this->request_id, 'MMNYTXN');
    }

    /**
     * Check if this mystery box was purchased directly
     */
    public function isPurchase()
    {
        return $this->source === self::SOURCE_PURCHASE;
    }

    /**
     * Check if this mystery box was redeemed using points
     */
    public function isPointRedemption()
    {
        return $this->source === self::SOURCE_POINT_REDEEM;
    }

    public function scopeOpened($query)
    {
        return $query->where('status', self::STATUS_OPENED);
    }

    /**
     * Scope for renewal awards
     */
    public function scopeRenewalAwards($query)
    {
        return $query->where('source', self::SOURCE_RENEWAL);
    }

    /**
     * Scope for Mixx payment awards
     */
    public function scopeMixxPaymentAwards($query)
    {
        return $query->where('source', self::SOURCE_MIXX_PAYMENT);
    }

    /**
     * Scope for direct purchases
     */
    public function scopePurchases($query)
    {
        return $query->where('source', self::SOURCE_PURCHASE);
    }

    /**
     * Scope for point redemptions
     */
    public function scopePointRedemptions($query)
    {
        return $query->where('source', self::SOURCE_POINT_REDEEM);
    }

    /**
     * Scope for all award types (renewal + mixx payment)
     */
    public function scopeAllAwards($query)
    {
        return $query->whereIn('source', [self::SOURCE_RENEWAL, self::SOURCE_MIXX_PAYMENT]);
    }

}
