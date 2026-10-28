<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileMoneyTransactionNotification extends Model
{
    use HasFactory;

    protected $table = 'mobile_money_transaction_notifications';

    protected $fillable = [
        'msisdn',
        'txn_type',
        'amount',
        'reference',
        'status',
        'publish_status',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'publish_status' => 'boolean',
        'amount' => 'decimal:2',
    ];

    /**
     * Scope to get unpublished notifications
     */
    public function scopeUnpublished($query)
    {
        return $query->where('publish_status', false);
    }

    /**
     * Scope to get published notifications
     */
    public function scopePublished($query)
    {
        return $query->where('publish_status', true);
    }

    /**
     * Scope to get notifications by MSISDN
     */
    public function scopeByMsisdn($query, $msisdn)
    {
        return $query->where('msisdn', $msisdn);
    }

    /**
     * Scope to get notifications by reference
     */
    public function scopeByReference($query, $reference)
    {
        return $query->where('reference', $reference);
    }

    /**
     * Scope to get notifications by transaction type
     */
    public function scopeByTxnType($query, $txnType)
    {
        return $query->where('txn_type', $txnType);
    }
} 