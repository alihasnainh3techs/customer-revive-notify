<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'name',
        'type',
        'subject',
        'body',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
