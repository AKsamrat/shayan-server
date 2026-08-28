<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'session_id', 'coupon_code'];

    /** Expose the computed discount so the cart/checkout can show it before an order is placed. */
    protected $appends = ['coupon_discount'];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $price = $item->variant ? $item->variant->price : ($item->product->discount_percentage > 0 ? $item->product->price : $item->product->price);
            return $price * $item->quantity;
        });
    }

    /**
     * Absolute discount amount for the currently applied coupon.
     * Uses the SAME formula as OrderController@store so the amount shown on the
     * cart/checkout matches what is actually charged when the order is placed.
     */
    public function getCouponDiscountAttribute(): float
    {
        if (empty($this->coupon_code)) {
            return 0.0;
        }

        $coupon = Coupon::where('code', $this->coupon_code)->first();
        if (!$coupon) {
            return 0.0;
        }

        $subtotal = (float) $this->subtotal;
        if ($subtotal <= 0) {
            return 0.0;
        }

        if ($coupon->type === 'percentage') {
            $discount = ($subtotal * (float) $coupon->value) / 100;
            if ($coupon->maximum_discount && $discount > (float) $coupon->maximum_discount) {
                $discount = (float) $coupon->maximum_discount;
            }
        } else {
            $discount = min((float) $coupon->value, $subtotal);
        }

        return round(min($discount, $subtotal), 2);
    }
}
