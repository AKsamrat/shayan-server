<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Slider;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\VendorShop;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionModulesSeeder::class);
        $this->call(RoleSeeder::class);
        $this->seedCurrencies();
        $this->seedLanguages();
        $this->seedUsers();
        $this->seedCategories();
        $this->seedBrands();
        $this->seedAttributes();
        $this->seedProducts();
        $this->seedVendor();
        $this->seedCms();
        $this->seedCoupons();
        $this->call(SettingsSeeder::class);
        $this->call(PaymentGatewaySeeder::class);
        $this->call(PaymentTransactionSeeder::class);
        $this->call(DeliveryPartnerSeeder::class);
        $this->call(ShippingZoneSeeder::class);
        $this->call(OrderSeeder::class);
    }

    private function seedUsers(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@shayanmart.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_verified' => true,
            'is_active' => true,
            'phone' => '+8801712345678',
        ]);
        Wallet::create(['user_id' => $admin->id, 'balance' => 0, 'currency' => 'BDT']);

        $customer = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'is_verified' => true,
            'is_active' => true,
            'phone' => '+8801712345679',
        ]);
        Wallet::create(['user_id' => $customer->id, 'balance' => 500, 'currency' => 'BDT']);

        collect([
            ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['name' => 'Rahman Ali', 'email' => 'rahman@example.com'],
            ['name' => 'Fatima Begum', 'email' => 'fatima@example.com'],
            ['name' => 'Kamal Hossain', 'email' => 'kamal@example.com'],
        ])->each(function ($data) {
            $user = User::create([
                ...$data,
                'password' => Hash::make('password'),
                'role' => 'customer',
                'is_verified' => true,
                'is_active' => true,
            ]);
            Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'BDT']);
        });
    }

    private function seedCategories(): void
    {
        $electronics = Category::updateOrCreate(['slug' => 'electronics'], ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Latest gadgets and devices', 'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=300&h=300&fit=crop', 'sort_order' => 1, 'is_active' => true]);
        $fashion = Category::updateOrCreate(['slug' => 'fashion'], ['name' => 'Fashion', 'slug' => 'fashion', 'description' => 'Trendy clothing and accessories', 'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?w=300&h=300&fit=crop', 'sort_order' => 2, 'is_active' => true]);
        $home = Category::updateOrCreate(['slug' => 'home-living'], ['name' => 'Home & Living', 'slug' => 'home-living', 'description' => 'Furniture and home decor', 'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=300&h=300&fit=crop', 'sort_order' => 3, 'is_active' => true]);
        $beauty = Category::updateOrCreate(['slug' => 'beauty-health'], ['name' => 'Beauty & Health', 'slug' => 'beauty-health', 'description' => 'Skincare and wellness products', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=300&h=300&fit=crop', 'sort_order' => 4, 'is_active' => true]);
        $sports = Category::updateOrCreate(['slug' => 'sports-outdoors'], ['name' => 'Sports & Outdoors', 'slug' => 'sports-outdoors', 'description' => 'Sports equipment and outdoor gear', 'image' => 'https://images.unsplash.com/photo-1461896836934-bd45ba8a0093?w=300&h=300&fit=crop', 'sort_order' => 5, 'is_active' => true]);
        $books = Category::updateOrCreate(['slug' => 'books-stationery'], ['name' => 'Books & Stationery', 'slug' => 'books-stationery', 'description' => 'Books, notebooks and office supplies', 'image' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=300&fit=crop', 'sort_order' => 6, 'is_active' => true]);

        Category::updateOrCreate(['slug' => 'smartphones'], ['name' => 'Smartphones', 'slug' => 'smartphones', 'parent_id' => $electronics->id, 'sort_order' => 1, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'laptops'], ['name' => 'Laptops', 'slug' => 'laptops', 'parent_id' => $electronics->id, 'sort_order' => 2, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'audio'], ['name' => 'Audio', 'slug' => 'audio', 'parent_id' => $electronics->id, 'sort_order' => 3, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'mens-clothing'], ['name' => 'Men\'s Clothing', 'slug' => 'mens-clothing', 'parent_id' => $fashion->id, 'sort_order' => 1, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'womens-clothing'], ['name' => 'Women\'s Clothing', 'slug' => 'womens-clothing', 'parent_id' => $fashion->id, 'sort_order' => 2, 'is_active' => true]);
        Category::updateOrCreate(['slug' => 'footwear'], ['name' => 'Footwear', 'slug' => 'footwear', 'parent_id' => $fashion->id, 'sort_order' => 3, 'is_active' => true]);
    }

    private function seedBrands(): void
    {
        Brand::updateOrCreate(['slug' => 'samsung'], ['name' => 'Samsung', 'slug' => 'samsung', 'description' => 'Korean electronics giant', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/24/Samsung_Logo.svg/2000px-Samsung_Logo.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'apple'], ['name' => 'Apple', 'slug' => 'apple', 'description' => 'Innovation at its finest', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fa/Apple_logo_black.svg/1667px-Apple_logo_black.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'nike'], ['name' => 'Nike', 'slug' => 'nike', 'description' => 'Just Do It', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a6/Logo_NIKE.svg/2000px-Logo_NIKE.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'adidas'], ['name' => 'Adidas', 'slug' => 'adidas', 'description' => 'Impossible is Nothing', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/Adidas_Logo.svg/2000px-Adidas_Logo.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'sony'], ['name' => 'Sony', 'slug' => 'sony', 'description' => 'Make.Believe', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c9/Sony_logo_%28white%29.svg/2000px-Sony_logo_%28white%29.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'lg'], ['name' => 'LG', 'slug' => 'lg', 'description' => 'Life\'s Good', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/LG_logo_%282015%29.svg/2000px-LG_logo_%282015%29.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'zara'], ['name' => 'Zara', 'slug' => 'zara', 'description' => 'Fast fashion leader', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/fd/Zara_Logo.svg/2000px-Zara_Logo.svg.png', 'is_active' => true]);
        Brand::updateOrCreate(['slug' => 'philips'], ['name' => 'Philips', 'slug' => 'philips', 'description' => 'Innovation and you', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c0/Philips_logo.svg/2000px-Philips_logo.svg.png', 'is_active' => true]);
    }

    private function seedAttributes(): void
    {
        $color = Attribute::create(['name' => 'Color', 'type' => 'color']);
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'color_code' => '#FF0000']);
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Blue', 'color_code' => '#0000FF']);
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Black', 'color_code' => '#000000']);
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'White', 'color_code' => '#FFFFFF']);
        AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Green', 'color_code' => '#00FF00']);

        $size = Attribute::create(['name' => 'Size', 'type' => 'dropdown']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XS']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'S']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL']);
        AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XXL']);

        $storage = Attribute::create(['name' => 'Storage', 'type' => 'dropdown']);
        AttributeValue::create(['attribute_id' => $storage->id, 'value' => '64GB']);
        AttributeValue::create(['attribute_id' => $storage->id, 'value' => '128GB']);
        AttributeValue::create(['attribute_id' => $storage->id, 'value' => '256GB']);
        AttributeValue::create(['attribute_id' => $storage->id, 'value' => '512GB']);
    }

    private function seedProducts(): void
    {
        $products = [
            ['name' => 'Samsung Galaxy S24 Ultra', 'slug' => 'samsung-galaxy-s24-ultra', 'price' => 129990, 'compare_price' => 139990, 'discount_percentage' => 7.14, 'description' => 'The ultimate Samsung flagship with AI features and titanium design.', 'short_description' => 'Samsung flagship with AI features', 'category_slug' => 'smartphones', 'brand_slug' => 'samsung', 'stock_quantity' => 50, 'is_featured' => true, 'is_best_seller' => true, 'average_rating' => 4.8, 'reviews_count' => 245, 'sales_count' => 1200, 'sku' => 'SAM-S24U', 'tags' => ['samsung', 'smartphone', 'flagship', '5g'], 'image' => 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&h=600&fit=crop'],
            ['name' => 'iPhone 15 Pro Max', 'slug' => 'iphone-15-pro-max', 'price' => 159990, 'compare_price' => 169990, 'discount_percentage' => 5.88, 'description' => 'Apple\'s most advanced iPhone with A17 Pro chip and titanium build.', 'short_description' => 'Apple flagship with A17 Pro', 'category_slug' => 'smartphones', 'brand_slug' => 'apple', 'stock_quantity' => 35, 'is_featured' => true, 'is_best_seller' => true, 'average_rating' => 4.9, 'reviews_count' => 312, 'sales_count' => 1500, 'sku' => 'APL-15PM', 'tags' => ['apple', 'iphone', 'smartphone', 'flagship'], 'image' => 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=600&h=600&fit=crop'],
            ['name' => 'MacBook Pro 16" M3 Max', 'slug' => 'macbook-pro-16-m3-max', 'price' => 299990, 'description' => 'The most powerful MacBook Pro ever with M3 Max chip.', 'short_description' => 'Powerful MacBook with M3 Max', 'category_slug' => 'laptops', 'brand_slug' => 'apple', 'stock_quantity' => 20, 'is_featured' => true, 'average_rating' => 4.9, 'reviews_count' => 89, 'sales_count' => 350, 'sku' => 'APL-MBP16', 'tags' => ['apple', 'macbook', 'laptop', 'pro'], 'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=600&fit=crop'],
            ['name' => 'Samsung 65" QLED 4K Smart TV', 'slug' => 'samsung-65-qled-4k-tv', 'price' => 129990, 'compare_price' => 149990, 'discount_percentage' => 13.33, 'description' => 'Stunning 4K QLED display with Quantum Processor.', 'short_description' => '65 inch QLED 4K Smart TV', 'category_slug' => 'electronics', 'brand_slug' => 'samsung', 'stock_quantity' => 15, 'is_flash_sale' => true, 'average_rating' => 4.7, 'reviews_count' => 67, 'sales_count' => 420, 'sku' => 'SAM-TV65', 'tags' => ['samsung', 'tv', 'qled', '4k'], 'image' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?w=600&h=600&fit=crop'],
            ['name' => 'Sony WH-1000XM5 Headphones', 'slug' => 'sony-wh1000xm5', 'price' => 29990, 'compare_price' => 34990, 'discount_percentage' => 14.29, 'description' => 'Industry-leading noise canceling headphones.', 'short_description' => 'Premium noise canceling headphones', 'category_slug' => 'audio', 'brand_slug' => 'sony', 'stock_quantity' => 80, 'is_featured' => true, 'is_best_seller' => true, 'average_rating' => 4.8, 'reviews_count' => 534, 'sales_count' => 2100, 'sku' => 'SNY-WH1K', 'tags' => ['sony', 'headphones', 'wireless', 'noise-canceling'], 'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop'],
            ['name' => 'Nike Air Max 270', 'slug' => 'nike-air-max-270', 'price' => 10990, 'compare_price' => 12990, 'discount_percentage' => 15.40, 'description' => 'Iconic lifestyle shoe with Max Air unit.', 'short_description' => 'Iconic lifestyle sneakers', 'category_slug' => 'footwear', 'brand_slug' => 'nike', 'stock_quantity' => 120, 'is_flash_sale' => true, 'average_rating' => 4.6, 'reviews_count' => 892, 'sales_count' => 3500, 'sku' => 'NIK-AM270', 'tags' => ['nike', 'sneakers', 'airmax', 'lifestyle'], 'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&h=600&fit=crop'],
            ['name' => 'Adidas Ultraboost 23', 'slug' => 'adidas-ultraboost-23', 'price' => 15990, 'description' => 'Responsive running shoes with Boost technology.', 'short_description' => 'Running shoes with Boost', 'category_slug' => 'footwear', 'brand_slug' => 'adidas', 'stock_quantity' => 90, 'is_featured' => true, 'average_rating' => 4.7, 'reviews_count' => 445, 'sales_count' => 2800, 'sku' => 'ADI-UB23', 'tags' => ['adidas', 'running', 'ultraboost'], 'image' => 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=600&h=600&fit=crop'],
            ['name' => 'Laptop Stand Adjustable', 'slug' => 'laptop-stand-adjustable', 'price' => 1990, 'compare_price' => 2490, 'discount_percentage' => 20.08, 'description' => 'Ergonomic aluminum laptop stand with adjustable height.', 'short_description' => 'Ergonomic laptop stand', 'category_slug' => 'electronics', 'brand_slug' => null, 'stock_quantity' => 200, 'is_best_seller' => true, 'average_rating' => 4.5, 'reviews_count' => 234, 'sales_count' => 1800, 'sku' => 'GEN-LPSTD', 'tags' => ['laptop', 'stand', 'ergonomic'], 'image' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=600&h=600&fit=crop'],
            ['name' => 'Wireless Bluetooth Speaker', 'slug' => 'wireless-bluetooth-speaker', 'price' => 3990, 'compare_price' => 4990, 'discount_percentage' => 20.04, 'description' => 'Portable waterproof Bluetooth speaker with 360° sound.', 'short_description' => 'Portable Bluetooth speaker', 'category_slug' => 'audio', 'brand_slug' => 'philips', 'stock_quantity' => 150, 'is_flash_sale' => true, 'average_rating' => 4.4, 'reviews_count' => 178, 'sales_count' => 950, 'sku' => 'PHI-BTS', 'tags' => ['speaker', 'bluetooth', 'portable', 'waterproof'], 'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&h=600&fit=crop'],
            ['name' => 'Men\'s Cotton Casual Shirt', 'slug' => 'mens-cotton-casual-shirt', 'price' => 1990, 'description' => 'Comfortable 100% cotton casual shirt for everyday wear.', 'short_description' => 'Cotton casual shirt', 'category_slug' => 'mens-clothing', 'brand_slug' => 'zara', 'stock_quantity' => 250, 'is_featured' => true, 'average_rating' => 4.3, 'reviews_count' => 156, 'sales_count' => 2200, 'sku' => 'ZAR-MCS', 'tags' => ['shirt', 'cotton', 'casual', 'men'], 'image' => 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=600&h=600&fit=crop'],
            ['name' => 'Women\'s Summer Dress', 'slug' => 'womens-summer-dress', 'price' => 2990, 'compare_price' => 3490, 'discount_percentage' => 14.33, 'description' => 'Elegant summer dress with floral print.', 'short_description' => 'Floral summer dress', 'category_slug' => 'womens-clothing', 'brand_slug' => 'zara', 'stock_quantity' => 180, 'is_featured' => true, 'average_rating' => 4.6, 'reviews_count' => 289, 'sales_count' => 1600, 'sku' => 'ZAR-WSD', 'tags' => ['dress', 'summer', 'floral', 'women'], 'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=600&h=600&fit=crop'],
            ['name' => 'Yoga Mat Premium', 'slug' => 'yoga-mat-premium', 'price' => 2990, 'description' => 'Non-slip premium yoga mat with alignment lines.', 'short_description' => 'Premium non-slip yoga mat', 'category_slug' => 'sports-outdoors', 'brand_slug' => null, 'stock_quantity' => 300, 'is_best_seller' => true, 'average_rating' => 4.7, 'reviews_count' => 456, 'sales_count' => 3200, 'sku' => 'GEN-YOGA', 'tags' => ['yoga', 'mat', 'fitness', 'exercise'], 'image' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=600&h=600&fit=crop'],
            ['name' => 'LED Desk Lamp', 'slug' => 'led-desk-lamp', 'price' => 3490, 'compare_price' => 3990, 'discount_percentage' => 12.53, 'description' => 'Dimmable LED desk lamp with wireless charging base.', 'short_description' => 'LED lamp with wireless charging', 'category_slug' => 'home-living', 'brand_slug' => 'philips', 'stock_quantity' => 120, 'is_flash_sale' => true, 'average_rating' => 4.5, 'reviews_count' => 134, 'sales_count' => 780, 'sku' => 'PHI-DLAMP', 'tags' => ['lamp', 'led', 'desk', 'wireless-charging'], 'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057ab6fe?w=600&h=600&fit=crop'],
            ['name' => 'Ceramic Cookware Set', 'slug' => 'ceramic-cookware-set', 'price' => 8990, 'description' => 'Premium 10-piece ceramic non-stick cookware set.', 'short_description' => '10-piece ceramic cookware', 'category_slug' => 'home-living', 'brand_slug' => null, 'stock_quantity' => 60, 'is_featured' => true, 'average_rating' => 4.8, 'reviews_count' => 98, 'sales_count' => 560, 'sku' => 'GEN-COOK', 'tags' => ['cookware', 'ceramic', 'kitchen', 'non-stick'], 'image' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=600&h=600&fit=crop'],
            ['name' => 'Vitamin C Serum', 'slug' => 'vitamin-c-serum', 'price' => 1290, 'compare_price' => 1490, 'discount_percentage' => 13.42, 'description' => 'Brightening vitamin C serum with hyaluronic acid.', 'short_description' => 'Brightening face serum', 'category_slug' => 'beauty-health', 'brand_slug' => null, 'stock_quantity' => 400, 'is_best_seller' => true, 'average_rating' => 4.6, 'reviews_count' => 678, 'sales_count' => 5400, 'sku' => 'GEN-VC', 'tags' => ['serum', 'vitamin-c', 'skincare', 'face'], 'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&h=600&fit=crop'],
            ['name' => 'Wireless Charging Pad', 'slug' => 'wireless-charging-pad', 'price' => 1990, 'description' => 'Fast wireless charging pad compatible with all Qi devices.', 'short_description' => 'Fast Qi wireless charger', 'category_slug' => 'electronics', 'brand_slug' => 'samsung', 'stock_quantity' => 200, 'average_rating' => 4.4, 'reviews_count' => 234, 'sales_count' => 1900, 'sku' => 'SAM-WCHG', 'tags' => ['charger', 'wireless', 'qi', 'fast-charging'], 'image' => 'https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=600&h=600&fit=crop'],
        ];

        foreach ($products as $data) {
            $category = Category::where('slug', $data['category_slug'])->first();
            $brand = $data['brand_slug'] ? Brand::where('slug', $data['brand_slug'])->first() : null;

            $product = Product::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'short_description' => $data['short_description'] ?? '',
                'price' => $data['price'],
                'compare_price' => $data['compare_price'] ?? null,
                'cost_price' => $data['price'] * 0.6,
                'discount_percentage' => $data['discount_percentage'] ?? null,
                'sku' => $data['sku'],
                'stock_quantity' => $data['stock_quantity'],
                'category_id' => $category->id,
                'brand_id' => $brand?->id,
                'is_active' => true,
                'is_featured' => $data['is_featured'] ?? false,
                'is_flash_sale' => $data['is_flash_sale'] ?? false,
                'is_best_seller' => $data['is_best_seller'] ?? false,
                'average_rating' => $data['average_rating'] ?? 0,
                'reviews_count' => $data['reviews_count'] ?? 0,
                'sales_count' => $data['sales_count'] ?? 0,
                'tags' => $data['tags'] ?? [],
            ]);

            ProductImage::create([
                'product_id' => $product->id,
                'image' => $data['image'] ?? 'products/placeholder.jpg',
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
            ]);
        }

        $customer = User::where('email', 'john@example.com')->first();
        $product = Product::first();
        if ($customer && $product) {
            Review::create([
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Amazing product!',
                'comment' => 'Absolutely love this product. Great quality and fast delivery.',
                'is_verified_purchase' => true,
            ]);
        }
    }

    private function seedVendor(): void
    {
        // Create vendor user
        $vendorUser = User::create([
            'name' => 'TechHub Vendor',
            'email' => 'vendor@shayanmart.com',
            'password' => Hash::make('password'),
            'role' => 'vendor',
            'is_verified' => true,
            'is_active' => true,
            'phone' => '+8801712345680',
        ]);
        Wallet::create(['user_id' => $vendorUser->id, 'balance' => 0, 'currency' => 'BDT']);

        // Assign vendor role
        $vendorRole = \App\Models\Role::where('slug', 'vendor')->first();
        if ($vendorRole) {
            $vendorUser->update(['role_id' => $vendorRole->id]);
        }

        // Create vendor shop
        $shop = VendorShop::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'TechHub Electronics',
            'shop_slug' => 'techhub-electronics',
            'shop_description' => 'Your one-stop shop for premium electronics and gadgets. We offer genuine products with warranty.',
            'contact_email' => 'vendor@shayanmart.com',
            'contact_phone' => '+8801712345680',
            'business_address' => '123 Tech Street, Banani',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1213',
            'tax_id' => 'TAX-12345678',
            'bank_name' => 'DBBL',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'TechHub Electronics',
            'bank_routing_number' => '09010',
            'commission_rate' => 10.00,
            'is_active' => true,
            'is_verified' => true,
        ]);

        // Assign some products to the vendor (first 5 products)
        $vendorProducts = Product::take(5)->get();
        foreach ($vendorProducts as $product) {
            $product->update(['vendor_id' => $shop->id]);
        }
        $shop->update(['total_products' => $vendorProducts->count()]);

        // Create sample orders with vendor items
        $customer = User::where('email', 'john@example.com')->first();
        if ($customer) {
            $statuses = ['pending', 'processing', 'shipped', 'delivered', 'delivered'];
            for ($i = 0; $i < 5; $i++) {
                $order = Order::create([
                    'user_id' => $customer->id,
                    'order_number' => 'ORD-V-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                    'status' => $statuses[$i],
                    'payment_status' => 'paid',
                    'payment_method' => 'cod',
                    'subtotal' => 0,
                    'shipping_cost' => 60,
                    'tax' => 0,
                    'total' => 0,
                ]);

                // Pick a random vendor product for each order
                $product = $vendorProducts[$i % $vendorProducts->count()];
                $qty = rand(1, 3);
                $itemTotal = $product->price * $qty;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'vendor_shop_id' => $shop->id,
                    'quantity' => $qty,
                    'price' => $product->price,
                    'total' => $itemTotal,
                ]);

                // Update order totals
                $order->update([
                    'subtotal' => $itemTotal,
                    'tax' => $itemTotal * 0.05,
                    'total' => $itemTotal + 60 + ($itemTotal * 0.05),
                ]);
            }

            // Update shop stats
            $totalRevenue = OrderItem::where('vendor_shop_id', $shop->id)->sum('total');
            $commission = $totalRevenue * ($shop->commission_rate / 100);
            $shop->update([
                'total_orders' => 5,
                'total_revenue' => $totalRevenue,
                'total_earnings' => $totalRevenue - $commission,
                'pending_payout' => $totalRevenue - $commission,
                'rating' => 4.8,
            ]);
        }
    }

    private function seedCms(): void
    {
        Slider::create(['title' => 'Mega Sale Up to 50% Off', 'subtitle' => 'Limited Time Offer', 'description' => 'Shop now and save big on electronics, fashion, and more.', 'image' => 'sliders/slider1.jpg', 'button_text' => 'Shop Now', 'link' => '/shop', 'is_active' => true, 'sort_order' => 1]);
        Slider::create(['title' => 'New Arrivals', 'subtitle' => 'Discover the Latest Trends', 'description' => 'Explore our newest collection of products.', 'image' => 'sliders/slider2.jpg', 'button_text' => 'Explore', 'link' => '/shop', 'is_active' => true, 'sort_order' => 2]);
        Slider::create(['title' => 'Free Shipping', 'subtitle' => 'On Orders Over ৳1000', 'description' => 'Enjoy free delivery on all orders above ৳1000.', 'image' => 'sliders/slider3.jpg', 'button_text' => 'Order Now', 'link' => '/shop', 'is_active' => true, 'sort_order' => 3]);

        Banner::create(['title' => 'Electronics Sale', 'subtitle' => 'Up to 40% Off', 'image' => 'banners/electronics.jpg', 'link' => '/categories/electronics', 'position' => 'home', 'is_active' => true, 'sort_order' => 1]);
        Banner::create(['title' => 'Fashion Week', 'subtitle' => 'New Collection', 'image' => 'banners/fashion.jpg', 'link' => '/categories/fashion', 'position' => 'home', 'is_active' => true, 'sort_order' => 2]);

        Testimonial::create(['name' => 'Rahim Ahmed', 'designation' => 'Business Owner', 'company' => 'Ahmed Trading', 'rating' => 5, 'comment' => 'Best online shopping experience in Bangladesh. Fast delivery and genuine products!', 'is_active' => true]);
        Testimonial::create(['name' => 'Sara Khan', 'designation' => 'Designer', 'company' => 'Creative Studio', 'rating' => 5, 'comment' => 'Amazing product quality and excellent customer support. Highly recommended!', 'is_active' => true]);
        Testimonial::create(['name' => 'Tanvir Hasan', 'designation' => 'Student', 'company' => 'BUET', 'rating' => 4, 'comment' => 'Great prices and fast delivery. The student discount is a nice touch!', 'is_active' => true]);
        Testimonial::create(['name' => 'Nusrat Jahan', 'designation' => 'Teacher', 'company' => 'Dhaka Academy', 'rating' => 5, 'comment' => 'I love shopping here. The variety of products is impressive.', 'is_active' => true]);

        Faq::create(['question' => 'What payment methods do you accept?', 'answer' => 'We accept credit/debit cards, bKash, Nagad, Rocket, and Cash on Delivery (COD).', 'category' => 'Payment', 'sort_order' => 1]);
        Faq::create(['question' => 'How long does delivery take?', 'answer' => 'Inside Dhaka: 1-2 business days. Outside Dhaka: 3-7 business days.', 'category' => 'Shipping', 'sort_order' => 2]);
        Faq::create(['question' => 'What is your return policy?', 'answer' => 'You can return products within 7 days of delivery if they are unused and in original packaging.', 'category' => 'Returns', 'sort_order' => 3]);
        Faq::create(['question' => 'Do you offer free shipping?', 'answer' => 'Yes! Free shipping on all orders above ৳1,000 within Bangladesh.', 'category' => 'Shipping', 'sort_order' => 4]);
        Faq::create(['question' => 'How can I track my order?', 'answer' => 'After shipping, you will receive a tracking number via SMS and email.', 'category' => 'Orders', 'sort_order' => 5]);
        Faq::create(['question' => 'Are products authentic?', 'answer' => 'Yes, all our products are 100% authentic and sourced directly from authorized distributors.', 'category' => 'General', 'sort_order' => 6]);

        Page::create(['title' => 'About Us', 'slug' => 'about', 'content' => '<h2>Welcome to Shayan Mart</h2><p>Shayan Mart is Bangladesh\'s leading online marketplace.</p>', 'is_active' => true]);
        Page::create(['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'content' => '<h2>Privacy Policy</h2><p>We respect your privacy.</p>', 'is_active' => true]);
        Page::create(['title' => 'Terms of Service', 'slug' => 'terms-of-service', 'content' => '<h2>Terms of Service</h2><p>Welcome to Shayan Mart.</p>', 'is_active' => true]);
    }

    private function seedCoupons(): void
    {
        Coupon::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10, 'minimum_order' => 500, 'maximum_discount' => 200, 'usage_limit' => 1000, 'is_active' => true, 'expires_at' => now()->addMonths(3)]);
        Coupon::create(['code' => 'FLAT200', 'type' => 'fixed', 'value' => 200, 'minimum_order' => 2000, 'usage_limit' => 500, 'is_active' => true, 'expires_at' => now()->addMonths(2)]);
        Coupon::create(['code' => 'EID25', 'type' => 'percentage', 'value' => 25, 'minimum_order' => 1000, 'maximum_discount' => 500, 'usage_limit' => 200, 'is_active' => true, 'expires_at' => now()->addMonth()]);
        Coupon::create(['code' => 'FREESHIP', 'type' => 'fixed', 'value' => 60, 'minimum_order' => 300, 'usage_limit' => 2000, 'is_active' => true, 'expires_at' => now()->addMonths(6)]);
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'native_symbol' => '৳', 'decimal_places' => 2, 'exchange_rate' => 1.00000000, 'is_default' => true, 'is_active' => true, 'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'native_symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 0.00910000, 'is_default' => false, 'is_active' => true, 'sort_order' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'native_symbol' => '€', 'decimal_places' => 2, 'exchange_rate' => 0.00840000, 'is_default' => false, 'is_active' => true, 'sort_order' => 3],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'native_symbol' => '£', 'decimal_places' => 2, 'exchange_rate' => 0.00720000, 'is_default' => false, 'is_active' => true, 'sort_order' => 4],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'native_symbol' => '₹', 'decimal_places' => 2, 'exchange_rate' => 0.76000000, 'is_default' => false, 'is_active' => true, 'sort_order' => 5],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'native_symbol' => '﷼', 'decimal_places' => 2, 'exchange_rate' => 0.03400000, 'is_default' => false, 'is_active' => true, 'sort_order' => 6],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'native_symbol' => 'د.إ', 'decimal_places' => 2, 'exchange_rate' => 0.03300000, 'is_default' => false, 'is_active' => true, 'sort_order' => 7],
        ];

        foreach ($currencies as $currency) {
            \App\Models\Currency::create($currency);
        }
    }

    private function seedLanguages(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'flag' => '🇺🇸', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true, 'sort_order' => 1],
            ['code' => 'bn', 'name' => 'Bengali', 'native_name' => 'বাংলা', 'flag' => '🇧🇩', 'direction' => 'ltr', 'is_default' => false, 'is_active' => true, 'sort_order' => 2],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'flag' => '🇸🇦', 'direction' => 'rtl', 'is_default' => false, 'is_active' => true, 'sort_order' => 3],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'flag' => '🇮🇳', 'direction' => 'ltr', 'is_default' => false, 'is_active' => true, 'sort_order' => 4],
        ];

        foreach ($languages as $language) {
            \App\Models\Language::create($language);
        }
    }
}
