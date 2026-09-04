<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'supplier_account_id',
        'purchase_id',
        'created_by',
        'transaction_number',
        'type',
        'amount',
        'amount_type',
        'balance_after',
        'payment_method',
        'reference_number',
        'transaction_date',
        'description',
        'notes',
        'attachment',
        'status',
        'supplier_purchase_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
        'status' => 'string',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account()
    {
        return $this->belongsTo(SupplierAccount::class, 'supplier_account_id');
    }

    public function purchase()
    {
        return $this->belongsTo(SupplierPurchase::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFormattedAmountAttribute()
    {
        $amount = $this->amount;
        $symbol = $this->amount_type === 'debit' ? '-' : '';
        return $symbol . ' ৳' . number_format($amount, 2);
    }

    public static function generateTransactionNumber()
    {
        // Find the maximum numeric part from all existing transaction numbers
        $maxNumber = static::where('transaction_number', 'like', 'TRX-%')
            ->orderByRaw('CAST(SUBSTRING(transaction_number, 5) AS UNSIGNED) DESC')
            ->value('transaction_number');
        
        if ($maxNumber) {
            $numericPart = substr($maxNumber, 4); // Remove 'TRX-'
            $number = (int) $numericPart;
            $number = max($number + 1, 1);
        } else {
            $number = 1;
        }
        
        // Ensure we never generate TRX-00000000
        $number = max($number, 1);
        
        return 'TRX-' . str_pad($number, 8, '0', STR_PAD_LEFT);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (!$transaction->transaction_number) {
                $transaction->transaction_number = self::generateTransactionNumber();
            }
            
            // Auto-set transaction date if not provided
            if (!$transaction->transaction_date) {
                $transaction->transaction_date = now()->toDateString();
            }
        });
    }
}
