<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_order',
        'maximum_discount',
        'category_id',
        'product_id',
        'usage_limit',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'category_id' => 'integer',
            'product_id' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /** The category this coupon is limited to, if any. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The product this coupon is limited to, if any. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
