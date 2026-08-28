<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPartner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'config',
        'status',
        'is_sandbox',
        'supported_areas',
        'supported_service_types',
        'cod_enabled',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'supported_areas' => 'array',
        'supported_service_types' => 'array',
        'is_sandbox' => 'boolean',
        'cod_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(DeliveryBooking::class, 'partner', 'slug');
    }
}
