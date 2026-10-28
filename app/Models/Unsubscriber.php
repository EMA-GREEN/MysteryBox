<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unsubscriber extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'unsubscribers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'msisdn',
        'channel',
        'mo_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'msisdn' => 'integer',
        'channel' => 'string',
        'mo_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Channel constants
     */
    const CHANNEL_SMS = 'SMS';
    const CHANNEL_APP = 'APP';
    const CHANNEL_ADMIN = 'ADMIN';

    /**
     * Scope a query to only include SMS channel unsubscribers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSms($query)
    {
        return $query->where('channel', self::CHANNEL_SMS);
    }

    /**
     * Scope a query to only include APP channel unsubscribers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApp($query)
    {
        return $query->where('channel', self::CHANNEL_APP);
    }

    /**
     * Scope a query to only include ADMIN channel unsubscribers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmin($query)
    {
        return $query->where('channel', self::CHANNEL_ADMIN);
    }
}

