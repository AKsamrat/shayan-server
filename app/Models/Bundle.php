<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bundle extends Model
{
    protected $fillable = [
        'name',
        'description',
        'badge',
        'discount_percentage',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discount_percentage' => 'float',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bundle_products')
            ->withPivot('sort_order')
            ->orderBy('bundle_products.sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }
}
