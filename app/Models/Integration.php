<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'configurations',
        'status',
    ];

    protected $casts = [
        'configurations' => 'array',
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
