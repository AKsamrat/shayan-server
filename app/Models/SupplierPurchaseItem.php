<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'supplier_inventory_id',
        'quantity',
        'unit_price',
        'total_price',
        'tax',
        'discount',
        'received_quantity',
        'batch_number',
        'expiry_date',
        'notes',
        'supplier_purchase_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'expiry_date' => 'date',
    ];

    public function purchase()
    {
        return $this->belongsTo(SupplierPurchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory()
    {
        return $this->belongsTo(SupplierInventory::class, 'supplier_inventory_id');
    }

    public function getRemainingQuantityAttribute()
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function getIsFullyReceivedAttribute()
    {
        return $this->received_quantity >= $this->quantity;
    }
}
