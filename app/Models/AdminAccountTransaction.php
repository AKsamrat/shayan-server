<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAccountTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'transaction_type',
        'amount',
        'balance_after',
        'description',
        'reference_id',
        'reference_type',
        'supplier_id',
        'to_account_id',
        'payment_method',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'datetime',
        'supplier_id' => 'integer',
        'to_account_id' => 'integer',
        'account_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(AdminAccount::class, 'account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(AdminAccount::class, 'to_account_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedAmountAttribute()
    {
        $amount = $this->amount;
        $symbol = $amount >= 0 ? '' : '-';
        return $symbol . ' ' . ($this->account->currency ?? 'USD') . ' ' . number_format(abs($amount), 2);
    }

    public function getFormattedBalanceAfterAttribute()
    {
        $balance = $this->balance_after;
        $symbol = $balance >= 0 ? '' : '-';
        return $symbol . ' ' . ($this->account->currency ?? 'USD') . ' ' . number_format(abs($balance), 2);
    }
}
