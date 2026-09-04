<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'contact_person',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_id',
        'business_license',
        'status',
        'notes',
        'opening_balance',
        'balance_type',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function inventory()
    {
        return $this->hasMany(SupplierInventory::class);
    }

    public function purchases()
    {
        return $this->hasMany(SupplierPurchase::class);
    }

    public function accounts()
    {
        return $this->hasMany(SupplierAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function getCurrentBalanceAttribute()
    {
        return $this->transactions()->sum(
            DB::raw("CASE WHEN amount_type = 'credit' THEN amount ELSE -amount END")
        ) + ($this->opening_balance ?? 0);
    }

    public function getBalanceTypeAttribute()
    {
        return $this->current_balance >= 0 ? 'credit' : 'debit';
    }

    public function getFormattedBalanceAttribute()
    {
        $balance = $this->current_balance;
        $symbol = $balance >= 0 ? '' : '-';
        return $symbol . ' ৳' . abs($balance);
    }
}
