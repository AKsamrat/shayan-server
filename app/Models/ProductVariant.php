<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'stock_quantity',
        'is_active',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attribute_values');
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['attributes'] = $this->whenLoaded('attributeValues', function () {
            return $this->attributeValues->map(fn($av) => [
                'attribute' => $av->attribute,
                'value' => $av,
            ])->values();
        }, []);
        return $data;
    }
}
