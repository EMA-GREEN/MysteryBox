<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalPaymentCallback extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'renewal_payment_callbacks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'request_id',
        'amount',
        'channel',
        'billing_system',
        'status',
        'details',
        'publish_status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'publish_status' => 'boolean',
        'details' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope a query to only include published callbacks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('publish_status', true);
    }

    /**
     * Scope a query to only include unpublished callbacks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpublished($query)
    {
        return $query->where('publish_status', false);
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
}