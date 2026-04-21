<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'enable_whatsapp',
        'user_id',
        'status',
        'disconnected_at',
        'session_id'
    ];

    protected $casts = [
        'disconnected_at' => 'date',
        'status' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
