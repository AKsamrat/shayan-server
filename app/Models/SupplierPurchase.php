<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'created_by',
        'purchase_date',
        'expected_delivery_date',
        'delivery_date',
        'status',
        'payment_status',
        'subtotal',
        'tax',
        'discount',
        'shipping_cost',
        'total',
        'amount_paid',
        'amount_due',
        'payment_method',
        'reference_number',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'expected_delivery_date' => 'date',
        'delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SupplierPurchaseItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierTransaction::class)->where('type', 'payment');
    }

    public function getTotalItemsAttribute()
    {
        return $this->items()->sum('quantity');
    }

    public function getReceivedItemsAttribute()
    {
        return $this->items()->sum('received_quantity');
    }

    public function getIsFullyReceivedAttribute()
    {
        return $this->received_items >= $this->total_items;
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->amount_paid >= $this->total;
    }

    public function getRemainingAmountAttribute()
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public static function generatePurchaseNumber()
    {
        $last = static::latest()->first();
        $number = $last ? (int) substr($last->purchase_number, 3) + 1 : 1;
        return 'PO-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
