<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use App\Models\Blog;
use App\Models\Slider;
use App\Models\Banner;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\OrderItem;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AdminAccount;
use App\Models\AdminAccountTransaction;
use App\Traits\ApiResponse;
use App\Models\Notification as NotificationModel;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\VendorShop;
use App\Models\DeliveryPartner;
use App\Models\DeliveryBooking;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\Brand;
use App\Models\FlashSale;
use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\ProductSpecification;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\RewardPoint;
use App\Services\Courier\CourierFraudChecker;
use App\Services\NotificationService;
use App\Services\RewardPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class AdminProductController extends AdminController
{
    public function getProducts(Request $request): JsonResponse
    {
        $this->ensurePermission('products.view');
        
        $query = Product::with(['category', 'brand', 'images']);

        if ($search = $request->search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $this->ensurePermission('products.create');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:products,slug',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'sku' => 'nullable|string',
            'stock_quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_flash_sale' => 'sometimes|boolean',
            'is_best_seller' => 'sometimes|boolean',
            'tags' => 'nullable|array',
            'colors' => 'nullable|array',
            'sizes' => 'nullable|array',
            'specifications' => 'nullable|array',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_featured'] = $validated['is_featured'] ?? false;
        $validated['is_flash_sale'] = $validated['is_flash_sale'] ?? false;
        $validated['is_best_seller'] = $validated['is_best_seller'] ?? false;

        // Extract colors, sizes, and specifications before creating product
        $colors = $validated['colors'] ?? [];
        $sizes = $validated['sizes'] ?? [];
        
        // Handle specifications - can be JSON string or array
        $specifications = [];
        if (!empty($validated['specifications'])) {
            if (is_string($validated['specifications'])) {
                $specifications = json_decode($validated['specifications'], true) ?? [];
            } elseif (is_array($validated['specifications'])) {
                $specifications = $validated['specifications'];
            }
        }
        
        unset($validated['colors'], $validated['sizes'], $validated['specifications']);

        $product = Product::create($validated);

        // Handle colors and sizes as attributes
        $this->syncProductAttributes($product, $colors, $sizes);

        // Handle specifications
        $this->syncProductSpecifications($product, $specifications);

        // Handle images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image' => $path,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        return $this->success($product->load(['images', 'specifications']), 'Product created', 201);
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission('products.edit');
        
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:products,slug,' . $id,
            'description' => 'sometimes|string',
            'short_description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'sku' => 'nullable|string',
            'stock_quantity' => 'sometimes|integer|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_flash_sale' => 'sometimes|boolean',
            'is_best_seller' => 'sometimes|boolean',
            'tags' => 'nullable|array',
            'colors' => 'nullable|array',
            'sizes' => 'nullable|array',
            'specifications' => 'nullable|array',
        ]);

        // Extract colors, sizes, and specifications before updating product
        $colors = $validated['colors'] ?? [];
        $sizes = $validated['sizes'] ?? [];
        
        // Handle specifications - can be JSON string or array
        $specifications = [];
        if (!empty($validated['specifications'])) {
            if (is_string($validated['specifications'])) {
                $specifications = json_decode($validated['specifications'], true) ?? [];
            } elseif (is_array($validated['specifications'])) {
                $specifications = $validated['specifications'];
            }
        }
        
        unset($validated['colors'], $validated['sizes'], $validated['specifications']);

        $product->update($validated);

        // Handle colors and sizes as attributes
        $this->syncProductAttributes($product, $colors, $sizes);

        // Handle specifications
        $this->syncProductSpecifications($product, $specifications);

        if ($request->hasFile('images')) {
            // Delete old images
            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img->image);
            }
            $product->images()->delete();

            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'image' => $path,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        return $this->success($product->fresh()->load(['images', 'specifications']), 'Product updated');
    }

    public function deleteProduct(int $id): JsonResponse
    {
        $this->ensurePermission('products.delete');
        $product = Product::findOrFail($id);
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->image);
        }
        $product->delete();
        return $this->success(null, 'Product deleted');
    }

}

