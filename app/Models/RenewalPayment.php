<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalPayment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'renewal_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'request_id',
        'payment_type',
        'amount',
        'status',
        'ack_status_code',
        'ack_description',
        'callback_status_code',
        'callback_description',
        'ack_received_time',
        'callback_received_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'ack_received_time' => 'datetime',
        'callback_received_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Payment status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Scope a query to only include pending payments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include successful payments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope a query to only include failed payments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to filter by payment type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $paymentType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPaymentType($query, $paymentType)
    {
        return $query->where('payment_type', $paymentType);
    }

    /**
     * Scope a query to filter by MSISDN.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $msisdn
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMsisdn($query, $msisdn)
    {
        return $query->where('msisdn', $msisdn);
    }

    /**
     * Check if payment has received acknowledgment.
     *
     * @return bool
     */
    public function hasAcknowledgment()
    {
        return !is_null($this->ack_received_time);
    }

    /**
     * Check if payment has received callback.
     *
     * @return bool
     */
    public function hasCallback()
    {
        return !is_null($this->callback_received_time);
    }

    /**
     * Get the formatted amount attribute.
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2);
    }

    /**
     * Update acknowledgment information.
     *
     * @param  string  $statusCode
     * @param  string  $description
     * @return bool
     */
    public function updateAcknowledgment($statusCode, $description)
    {
        return $this->update([
            'ack_status_code' => $statusCode,
            'ack_description' => $description,
            'ack_received_time' => now(),
        ]);
    }

    /**
     * Update callback information.
     *
     * @param  string  $statusCode
     * @param  string  $description
     * @return bool
     */
    public function updateCallback($statusCode, $description)
    {
        return $this->update([
            'callback_status_code' => $statusCode,
            'callback_description' => $description,
            'callback_received_time' => now(),
        ]);
    }
}