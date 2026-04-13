<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'subject',
        'body',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
