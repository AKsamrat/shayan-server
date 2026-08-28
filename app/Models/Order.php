<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'discount',
        'shipping_cost',
        'tax',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'transaction_id',
        'shipping_address_id',
        'billing_address_id',
        'tracking_number',
        'shipping_method',
        'notes',
        'coupon_code',
        'delivery_boy_id',
        'delivery_notes',
        'assigned_at',
        'picked_up_at',
        'shipped_at',
        'delivered_at',
        'return_reason',
        'return_notes',
        'return_requested_at',
        'refunded_at',
        'refund_amount',
        'refund_reason',
        'refund_method',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'return_requested_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }
    public function billingAddress()
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function deliveryBoy()
    {
        return $this->belongsTo(User::class, 'delivery_boy_id');
    }

    public function deliveryBooking()
    {
        return $this->hasOne(DeliveryBooking::class)->latestOfMany();
    }
}
