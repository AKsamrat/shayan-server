<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_type',
        'account_number',
        'bank_name',
        'branch_name',
        'routing_number',
        'initial_balance',
        'current_balance',
        'currency',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(AdminAccountTransaction::class, 'account_id');
    }

    public function getFormattedBalanceAttribute()
    {
        $balance = $this->current_balance;
        $symbol = $balance >= 0 ? '' : '-';
        return $symbol . ' ' . $this->currency . ' ' . number_format(abs($balance), 2);
    }
}
