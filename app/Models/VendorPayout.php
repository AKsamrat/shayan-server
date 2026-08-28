<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_shop_id',
        'amount',
        'status',
        'payment_method',
        'transaction_id',
        'notes',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function vendorShop()
    {
        return $this->belongsTo(VendorShop::class, 'vendor_shop_id');
    }
}
