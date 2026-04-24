<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmtpConfiguration extends Model
{
    protected $fillable = [
        'user_id',
        'service',
        'smtp_host',
        'port',
        'security_type',
        'username',
        'password',
        'custom_from_email',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
