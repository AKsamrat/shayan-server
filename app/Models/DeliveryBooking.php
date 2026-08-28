<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_number',
        'partner',
        'tracking_id',
        'status',
        'pickup_address',
        'delivery_address',
        'recipient_name',
        'recipient_phone',
        'cod_amount',
        'shipping_fee',
        'estimated_delivery',
        'booked_at',
        'picked_at',
        'delivered_at',
    ];

    protected $casts = [
        'cod_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'booked_at' => 'datetime',
        'picked_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Named partnerInfo (not "partner") to avoid clashing with the "partner" slug column.
    public function partnerInfo()
    {
        return $this->belongsTo(DeliveryPartner::class, 'partner', 'slug');
    }
}
