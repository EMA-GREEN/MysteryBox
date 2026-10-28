<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpushSubsInitNotification extends Model
{
    protected $table = 'mpush_subs_init_notifications';

    protected $primaryKey = 'ID';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'MSISDN',
        'CHANNEL',
        'USSD_DATA',
        'IS_OUTGOING',
    ];

    protected $casts = [
        'MSISDN' => 'integer',
        'IS_OUTGOING' => 'integer',
        'CREATED_AT' => 'datetime',
    ];

    const CHANNEL_SMS = 'SMS';
    const CHANNEL_USSD = 'USSD';
    const CHANNEL_APP = 'APP';

    public function scopePendingOutgoing($query)
    {
        return $query->where('IS_OUTGOING', 1);
    }
}
