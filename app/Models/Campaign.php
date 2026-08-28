<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'status',
        'image',
        'coupon_code',
        'discount_percentage',
        'budget',
        'spent',
        'target_audience',
        'reached_audience',
        'clicks',
        'conversions',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];
}
