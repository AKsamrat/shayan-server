<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_branch',
        'bank_routing_number',
        'account_type',
        'current_balance',
        'balance_type',
        'notes',
        'is_default',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function transactions()
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    public function getFormattedBalanceAttribute()
    {
        $balance = $this->current_balance;
        $symbol = $balance >= 0 ? '' : '-';
        return $symbol . ' ৳' . number_format(abs($balance), 2);
    }
}
