<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AirtimeDisbursementCallback extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_id',
        'msisdn',
        'amount',
        'status',
        'message',
        'transaction_id',
        'balance',
        'raw_response',
        'callback_source',
        'ip_address',
        'callback_received_at',
        'publish_status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'raw_response' => 'array',
        'callback_received_at' => 'datetime',
        'publish_status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the related airtime disbursement record
     */
    public function disbursement()
    {
        return $this->belongsTo(WinnerAirtimeDisbursement::class, 'reference_id', 'reference_id');
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by reference ID
     */
    public function scopeByReferenceId($query, $referenceId)
    {
        return $query->where('reference_id', $referenceId);
    }

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
}
