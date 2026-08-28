<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'config',
        'status',
        'test_mode',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'test_mode' => 'boolean',
    ];
}
