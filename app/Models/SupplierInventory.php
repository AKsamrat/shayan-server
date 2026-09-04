<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'quantity',
        'minimum_quantity',
        'location',
        'expiry_date',
        'batch_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'quantity' => 'integer',
        'minimum_quantity' => 'integer',
        'expiry_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(SupplierPurchaseItem::class, 'supplier_inventory_id');
    }

    public function getStockStatusAttribute()
    {
        if ($this->quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->quantity <= ($this->minimum_quantity ?? 0)) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    public function getStockValueAttribute()
    {
        return $this->cost_price * $this->quantity;
    }
}
