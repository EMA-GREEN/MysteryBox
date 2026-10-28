<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalContentNotification extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'renewal_content_notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'request_id',
        'payment_type',
        'text',
        'publish_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'msisdn' => 'integer',
        'publish_status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    /**
     * Scope a query to filter by MSISDN.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $msisdn
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMsisdn($query, $msisdn)
    {
        return $query->where('msisdn', $msisdn);
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
     * Scope a query to filter by request ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $requestId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRequestId($query, $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    /**
     * Mark notification as published.
     *
     * @return bool
     */
    public function markAsPublished()
    {
        return $this->update(['publish_status' => true]);
    }

    /**
     * Mark notification as unpublished.
     *
     * @return bool
     */
    public function markAsUnpublished()
    {
        return $this->update(['publish_status' => false]);
    }

    /**
     * Get the truncated text attribute for display purposes.
     *
     * @param  int  $length
     * @return string
     */
    public function getTruncatedText($length = 100)
    {
        return strlen($this->text) > $length 
            ? substr($this->text, 0, $length) . '...' 
            : $this->text;
    }
}