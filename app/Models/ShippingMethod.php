<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'name',
        'description',
        'rate_type',
        'base_rate',
        'per_kg_rate',
        'min_order_amount',
        'max_order_amount',
        'min_weight',
        'max_weight',
        'estimated_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'per_kg_rate' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_order_amount' => 'decimal:2',
        'min_weight' => 'decimal:2',
        'max_weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }
}
