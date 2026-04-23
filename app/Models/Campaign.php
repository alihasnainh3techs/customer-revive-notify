<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_name',
        'campaign_status',
        'campaign_type',
        'discount_code',
        'shopify_discount_id',
        'schedule_type',
        'monthly_frequency',
        'monthly_validity',
        'custom_start_date',
        'custom_validity',
        'selected_products',
        'discount_type',
        'discount_rules',
        'customer_filters',
        'message_template_id',
        'email_template_id',
    ];

    protected $casts = [
        'selected_products' => 'array',
        'discount_rules'    => 'array',
        'customer_filters'  => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
