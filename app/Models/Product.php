<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost_price',
        'discount_percentage',
        'sku',
        'barcode',
        'stock_quantity',
        'low_stock_threshold',
        'category_id',
        'sub_category_id',
        'child_category_id',
        'brand_id',
        'video_url',
        'is_active',
        'is_featured',
        'is_new_arrival',
        'is_flash_sale',
        'is_best_seller',
        'average_rating',
        'reviews_count',
        'sales_count',
        'tags',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_flash_sale' => 'boolean',
            'is_best_seller' => 'boolean',
            'views_count' => 'integer',
            'average_rating' => 'decimal:1',
            'reviews_count' => 'integer',
            'sales_count' => 'integer',
            'tags' => 'array',
        ];
    }

    protected $appends = ['colors', 'sizes'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
            ->withTimestamps();
    }

    public function getDiscountedPriceAttribute(): ?string
    {
        if ($this->discount_percentage && $this->discount_percentage > 0) {
            return number_format($this->price * (1 - $this->discount_percentage / 100), 2, '.', '');
        }
        return null;
    }

    public function getColorsAttribute(): array
    {
        return $this->attributes()
            ->where('attributes.name', 'Color')
            ->with('values')
            ->get()
            ->flatMap(fn($attr) => $attr->values->pluck('value')->toArray())
            ->unique()
            ->values()
            ->toArray();
    }

    public function getSizesAttribute(): array
    {
        return $this->attributes()
            ->where('attributes.name', 'Size')
            ->with('values')
            ->get()
            ->flatMap(fn($attr) => $attr->values->pluck('value')->toArray())
            ->unique()
            ->values()
            ->toArray();
    }

    public function vendorShop()
    {
        return $this->belongsTo(VendorShop::class, 'vendor_id');
    }
}
