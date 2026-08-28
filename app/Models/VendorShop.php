<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorShop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'shop_slug',
        'shop_description',
        'shop_logo',
        'shop_banner',
        'shop_url',
        'contact_email',
        'contact_phone',
        'business_address',
        'city',
        'country',
        'postal_code',
        'tax_id',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'bank_routing_number',
        'commission_rate',
        'is_active',
        'is_verified',
        'rating',
        'total_sales',
        'total_products',
        'total_orders',
        'total_revenue',
        'total_earnings',
        'pending_payout',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'rating' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'pending_payout' => 'decimal:2',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'total_sales' => 'integer',
            'total_products' => 'integer',
            'total_orders' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function payouts()
    {
        return $this->hasMany(VendorPayout::class, 'vendor_shop_id');
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, OrderItem::class, 'vendor_shop_id', 'id', 'id', 'order_id');
    }
}
