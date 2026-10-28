<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotification extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_notifications';

    /**
     * Possible values for payment_type
     */
    const PAYMENT_TYPE_PURCHASE = 'PurchaseBox';
    const PAYMENT_TYPE_OPEN = 'OpenBox';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'payment_type',
        'amount',
        'box_type',
        'channel',
        'request_id',
        'status',
        'publish_status',
        'box_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'publish_status' => 'boolean',
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

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
     * Scope a query to only include published notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('publish_status', true);
    }

    /**
     * Scope a query to only include unpublished notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpublished($query)
    {
        return $query->where('publish_status', false);
    }
} 