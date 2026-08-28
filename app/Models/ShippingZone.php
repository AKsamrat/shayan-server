<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'countries',
        'states',
        'cities',
        'zip_codes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'countries' => 'array',
        'states' => 'array',
        'cities' => 'array',
        'zip_codes' => 'array',
        'is_active' => 'boolean',
    ];

    public function methods()
    {
        return $this->hasMany(ShippingMethod::class, 'zone_id');
    }
}
