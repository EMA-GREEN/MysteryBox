<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessToken extends Model
{
    protected $table = 'user_access_token';

    protected $fillable = [
        'ACCESS_TOKEN',
        'REFERESH_TOKEN',
        'CREATED_AT',
        'EXPIRES_AT',
    ];

    public $timestamps = false;
} 