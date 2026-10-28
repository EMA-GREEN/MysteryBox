<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenewalSubscriber extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'renewal_subscribers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'activity_status',
        'dnd_status',
        'priority',
        'retry_status',
        'retry_count',
        'publish_status',
        'last_renewal_billing',
        'next_retrial_period',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'msisdn' => 'integer',
        'activity_status' => 'string',
        'dnd_status' => 'string',
        'priority' => 'integer',
        'retry_count' => 'integer',
        'publish_status' => 'boolean',
        'last_renewal_billing' => 'datetime',
        'next_retrial_period' => 'datetime',
        'record_date' => 'datetime',
    ];

    /**
     * Activity status constants
     */
    const ACTIVITY_STATUS_ACTIVE = 'ACTIVE';
    const ACTIVITY_STATUS_INACTIVE = 'INACTIVE';

    /**
     * DND status constants
     */
    const DND_STATUS_ON = 'ON';
    const DND_STATUS_OFF = 'OFF';

    /**
     * Scope a query to only include active subscribers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('activity_status', self::ACTIVITY_STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include subscribers with DND off.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDndOff($query)
    {
        return $query->where('dnd_status', self::DND_STATUS_OFF);
    }

    /**
     * Scope a query to only include unpublished subscribers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpublished($query)
    {
        return $query->where('publish_status', false);
    }

    /**
     * Scope a query to filter by retry count less than specified value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $retryCount
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRetryCountLessThan($query, $retryCount)
    {
        return $query->where('retry_count', '<', $retryCount);
    }

    /**
     * Scope a query to filter by last renewal billing older than one week or null.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLastRenewalOlderThanWeekOrNull($query)
    {
        return $query->where(function ($q) {
            $q->where('last_renewal_billing', '<', now()->subWeek())
              ->orWhereNull('last_renewal_billing');
        });
    }

    /**
     * Scope a query to filter by last renewal billing within the last week.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLastRenewalWithinWeek($query)
    {
        return $query->whereDate('last_renewal_billing', '<', now()->toDateString())
                     ->where('last_renewal_billing', '>=', now()->subWeek());
    }

    /**
     * Increment retry count and set publish status.
     *
     * @return bool
     */
    public function incrementRetryAndPublish()
    {
        return $this->update([
            'publish_status' => true,
            'retry_count' => $this->retry_count + 1
        ]);
    }
}