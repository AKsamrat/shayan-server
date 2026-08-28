<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\VendorShop;
use App\Models\VendorPayout;
use App\Traits\ApiResponse;
use App\Models\Notification as NotificationModel;
use App\Services\NotificationService;
use App\Services\RewardPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    use ApiResponse;

    /**
     * Get the authenticated vendor's shop profile.
     */
    public function getShop(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        return $this->success($shop);
    }

    /**
     * Update the vendor's shop profile.
     */
    public function updateShop(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $validated = $request->validate([
            'shop_name' => 'sometimes|string|max:255',
            'shop_slug' => 'sometimes|string|max:255|unique:vendor_shops,shop_slug,' . $shop->id,
            'shop_description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'business_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_routing_number' => 'nullable|string|max:50',
        ]);

        // Auto-generate slug from shop_name if name changed and slug not provided
        if (isset($validated['shop_name']) && !isset($validated['shop_slug'])) {
            $validated['shop_slug'] = Str::slug($validated['shop_name']);
            // Ensure unique slug
            $baseSlug = $validated['shop_slug'];
            $counter = 1;
            while (VendorShop::where('shop_slug', $validated['shop_slug'])->where('id', '!=', $shop->id)->exists()) {
                $validated['shop_slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $shop->update($validated);

        return $this->success($shop->fresh(), 'Shop profile updated successfully');
    }

    /**
     * Upload vendor shop logo.
     */
    public function updateShopLogo(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        // Delete old logo
        if ($shop->shop_logo && Storage::disk('public')->exists($shop->shop_logo)) {
            Storage::disk('public')->delete($shop->shop_logo);
        }

        $path = $request->file('logo')->store('vendor/logos', 'public');
        $shop->update(['shop_logo' => $path]);

        return $this->success($shop->fresh(), 'Logo updated successfully');
    }

    /**
     * Upload vendor shop banner.
     */
    public function updateShopBanner(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $request->validate([
            'banner' => 'required|image|max:4096',
        ]);

        // Delete old banner
        if ($shop->shop_banner && Storage::disk('public')->exists($shop->shop_banner)) {
            Storage::disk('public')->delete($shop->shop_banner);
        }

        $path = $request->file('banner')->store('vendor/banners', 'public');
        $shop->update(['shop_banner' => $path]);

        return $this->success($shop->fresh(), 'Banner updated successfully');
    }

    // ==================== DASHBOARD ====================

    /**
     * Get vendor dashboard statistics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $products = Product::where('vendor_id', $shop->id);
        $orderItems = OrderItem::where('vendor_shop_id', $shop->id);
        $orders = Order::whereIn('id', $orderItems->pluck('order_id')->unique());

        // Counts
        $totalProducts = $products->count();
        $activeProducts = (clone $products)->where('is_active', true)->count();

        $totalOrders = (clone $orderItems)->distinct('order_id')->count('order_id');
        $pendingOrders = (clone $orders)->where('status', 'pending')->count();
        $processingOrders = (clone $orders)->where('status', 'processing')->count();
        $shippedOrders = (clone $orders)->where('status', 'shipped')->count();
        $deliveredOrders = (clone $orders)->where('status', 'delivered')->count();

        // Revenue
        $totalRevenue = (clone $orderItems)->sum('total');
        $commissionRate = $shop->commission_rate;
        $commission = $totalRevenue * ($commissionRate / 100);
        $totalEarnings = $totalRevenue - $commission;

        // Reviews
        $productIds = $products->pluck('id');
        $totalReviews = Review::whereIn('product_id', $productIds)->count();
        $averageRating = Review::whereIn('product_id', $productIds)->avg('rating') ?? 0;

        // Chart data (last 30 days)
        $revenueChart = (clone $orderItems)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(total) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => ['date' => $item->date, 'amount' => (float) $item->amount]);

        $ordersChart = (clone $orderItems)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT order_id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => ['date' => $item->date, 'count' => (int) $item->count]);

        return $this->success([
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'shipped_orders' => $shippedOrders,
            'delivered_orders' => $deliveredOrders,
            'total_revenue' => (float) $totalRevenue,
            'total_earnings' => (float) $totalEarnings,
            'pending_payout' => (float) $shop->pending_payout,
            'commission_rate' => (float) $commissionRate,
            'total_reviews' => $totalReviews,
            'average_rating' => round((float) $averageRating, 1),
            'revenue_chart' => $revenueChart,
            'orders_chart' => $ordersChart,
        ]);
    }

    // ==================== PRODUCTS ====================

    /**
     * List vendor's products with pagination, search, and status filter.
     */
    public function getProducts(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $query = Product::with('images', 'category', 'brand')
            ->where('vendor_id', $shop->id);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true)->where('is_approved', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($status === 'rejected') {
                $query->where('is_approved', false)->whereNotNull('rejection_reason');
            }
        }

        $query->latest();
        $products = $this->paginated($query);

        return $this->success($products);
    }

    /**
     * Get single vendor product.
     */
    public function getProduct(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $product = Product::with('images', 'category', 'brand', 'variants')
            ->where('vendor_id', $shop->id)
            ->findOrFail($id);

        return $this->success($product);
    }

    /**
     * Create a new product for the vendor.
     */
    public function createProduct(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'child_category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'tags' => 'nullable|array',
            'video_url' => 'nullable|string|max:500',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
            'images' => 'required|array|min:1',
            'images.*' => 'image|max:5120',
        ]);

        // Generate slug
        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        // Extract images from validated data
        $images = $validated['images'] ?? [];
        unset($validated['images']);

        // Create product
        $product = Product::create([
            ...$validated,
            'slug' => $slug,
            'vendor_id' => $shop->id,
            'is_approved' => true,
            'is_active' => true,
            'average_rating' => 0,
            'reviews_count' => 0,
            'sales_count' => 0,
        ]);

        // Store images
        if (!empty($images)) {
            foreach ($images as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path,
                    'alt_text' => $validated['name'],
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        // Update shop total products
        $shop->update(['total_products' => $shop->products()->count()]);

        return $this->success(
            $product->load('images', 'category', 'brand'),
            'Product created successfully',
            201
        );
    }

    /**
     * Update a vendor product.
     */
    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $product = Product::where('vendor_id', $shop->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'short_description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'sku' => 'sometimes|required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:categories,id',
            'child_category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'tags' => 'nullable|array',
            'video_url' => 'nullable|string|max:500',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_keywords' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer|exists:product_images,id',
        ]);

        // Extract and unset special fields
        $images = $validated['images'] ?? null;
        $removeImages = $validated['remove_images'] ?? [];
        unset($validated['images'], $validated['remove_images']);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $product->name) {
            $slug = Str::slug($validated['name']);
            $baseSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        $product->update($validated);

        // Remove specified images
        if (!empty($removeImages)) {
            $imagesToDelete = ProductImage::whereIn('id', $removeImages)
                ->where('product_id', $product->id)
                ->get();
            foreach ($imagesToDelete as $img) {
                if ($img->image && Storage::disk('public')->exists($img->image)) {
                    Storage::disk('public')->delete($img->image);
                }
                $img->delete();
            }
        }

        // Add new images
        if (!empty($images)) {
            $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? -1;
            foreach ($images as $index => $image) {
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path,
                    'alt_text' => $product->name,
                    'sort_order' => $maxSort + $index + 1,
                    'is_primary' => false,
                ]);
            }
        }

        return $this->success(
            $product->load('images', 'category', 'brand'),
            'Product updated successfully'
        );
    }

    /**
     * Delete a vendor product.
     */
    public function deleteProduct(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $product = Product::where('vendor_id', $shop->id)->findOrFail($id);

        // Delete product images from storage
        foreach ($product->images as $image) {
            if ($image->image && Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }
            $image->delete();
        }

        $product->delete();

        // Update shop total products
        $shop->update(['total_products' => $shop->products()->count()]);

        return $this->success(null, 'Product deleted successfully');
    }

    // ==================== ORDERS ====================

    /**
     * Get vendor's orders with pagination and status filter.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        // Get order IDs that contain vendor's products
        $orderIds = OrderItem::where('vendor_shop_id', $shop->id)
            ->distinct('order_id')
            ->pluck('order_id');

        $query = Order::with([
            'user:id,name,email,avatar',
            'items' => function ($q) use ($shop) {
                $q->where('vendor_shop_id', $shop->id)->with('product:id,name,slug,images');
            },
            'shippingAddress',
        ])
            ->whereIn('id', $orderIds);

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $query->latest();
        $orders = $this->paginated($query);

        // Transform orders to include vendor-specific earnings
        $orders['data'] = collect($orders['data'])->map(function ($order) use ($shop) {
            $vendorItems = $order->items->filter(fn($item) => $item->vendor_shop_id === $shop->id);
            $vendorSubtotal = $vendorItems->sum('total');
            $commission = $vendorSubtotal * ($shop->commission_rate / 100);

            return [
                ...$order->toArray(),
                'commission' => round($commission, 2),
                'vendor_earnings' => round($vendorSubtotal - $commission, 2),
                'items' => $vendorItems,
            ];
        })->toArray();

        return $this->success($orders);
    }

    /**
     * Get single order details for vendor.
     */
    public function getOrder(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $order = Order::with([
            'user:id,name,email,avatar',
            'items' => function ($q) use ($shop) {
                $q->where('vendor_shop_id', $shop->id)->with('product:id,name,slug,images');
            },
            'shippingAddress',
        ])->findOrFail($id);

        // Verify this order has vendor's items
        $hasVendorItems = $order->items->contains('vendor_shop_id', $shop->id);
        if (!$hasVendorItems) {
            return $this->error('Order not found', 404);
        }

        $vendorSubtotal = $order->items->where('vendor_shop_id', $shop->id)->sum('total');
        $commission = $vendorSubtotal * ($shop->commission_rate / 100);

        return $this->success([
            ...$order->toArray(),
            'commission' => round($commission, 2),
            'vendor_earnings' => round($vendorSubtotal - $commission, 2),
        ]);
    }

    /**
     * Update order status (vendor can only update orders containing their products).
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);

        // Verify order has vendor's items
        $hasVendorItems = OrderItem::where('order_id', $id)
            ->where('vendor_shop_id', $shop->id)
            ->exists();

        if (!$hasVendorItems) {
            return $this->error('Order not found', 404);
        }

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;
        
        $order->update(['status' => $validated['status']]);

        // Set timestamps based on status
        match ($validated['status']) {
            'shipped' => Schema::hasColumn('orders', 'shipped_at') ? $order->update(['shipped_at' => now()]) : null,
            'delivered' => Schema::hasColumn('orders', 'delivered_at') ? $order->update(['delivered_at' => now()]) : null,
            default => null,
        };

        // Award reward points when order is delivered and payment is successful
        $wasAlreadyDeliveredAndPaid = ($oldStatus === 'delivered' && $oldPaymentStatus === 'paid');
        $isNowDeliveredAndPaid = ($order->status === 'delivered' && $order->payment_status === 'paid');
        
        if ($isNowDeliveredAndPaid && !$wasAlreadyDeliveredAndPaid) {
            $service = app(\App\Services\RewardPointService::class);
            if ($order->user) {
                $service->awardPoints($order->user, $order);
            }
        }

        // Send status change notification
        app(NotificationService::class)->orderStatusChanged($order->fresh(), $oldStatus);

        return $this->success(
            $order->load('user:id,name,email'),
            'Order status updated successfully'
        );
    }

    /**
     * Add tracking number to order.
     */
    public function addTrackingNumber(Request $request, int $id): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        $validated = $request->validate([
            'tracking_number' => 'required|string|max:255',
        ]);

        $order = Order::findOrFail($id);

        // Verify order has vendor's items
        $hasVendorItems = OrderItem::where('order_id', $id)
            ->where('vendor_shop_id', $shop->id)
            ->exists();

        if (!$hasVendorItems) {
            return $this->error('Order not found', 404);
        }

        $order->update(['tracking_number' => $validated['tracking_number']]);

        // Send shipping update notification
        app(NotificationService::class)->orderStatusChanged($order->fresh());

        return $this->success($order, 'Tracking number added successfully');
    }

    // ==================== PAYOUTS ====================

    /**
     * Get vendor payout history.
     */
    public function getPayouts(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $payouts = VendorPayout::where('vendor_shop_id', $shop->id)->latest();
        $result = $this->paginated($payouts);

        return $this->success($result);
    }

    /**
     * Request a payout.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validated['amount'] > $shop->pending_payout) {
            return $this->error('Insufficient balance for payout');
        }

        $payout = VendorPayout::create([
            'vendor_shop_id' => $shop->id,
            'amount' => $validated['amount'],
            'status' => 'pending',
            'payment_method' => $shop->bank_name ? 'bank_transfer' : 'pending_method',
        ]);

        // Reduce pending payout
        $shop->update([
            'pending_payout' => $shop->pending_payout - $validated['amount'],
        ]);

        return $this->success($payout, 'Payout request submitted', 201);
    }

    // ==================== REVIEWS ====================

    /**
     * Get reviews for vendor's products.
     */
    public function getReviews(Request $request): JsonResponse
    {
        $shop = $request->user()->vendorShop;

        if (!$shop) {
            return $this->error('Vendor shop not found', 404);
        }

        $productIds = Product::where('vendor_id', $shop->id)->pluck('id');

        $reviews = Review::with('user:id,name,avatar', 'product:id,name,images')
            ->whereIn('product_id', $productIds)
            ->latest();

        $result = $this->paginated($reviews);

        return $this->success($result);
    }

    // ==================== NOTIFICATIONS ====================

    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = NotificationModel::where('user_id', $user->id);
        $result = $query->latest()->get();
        return $this->success($result);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $notification = NotificationModel::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $notification->update(['is_read' => true]);
        return $this->success($notification->fresh());
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        NotificationModel::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return $this->success(null, 'All notifications marked as read');
    }

    // ==================== CATEGORIES & BRANDS (for product forms) ====================

    /**
     * Get all categories (for product creation form).
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories);
    }

    /**
     * Get all brands (for product creation form).
     */
    public function getBrands(): JsonResponse
    {
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return $this->success($brands);
    }
}
