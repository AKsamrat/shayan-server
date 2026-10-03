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

class AdminController extends Controller
{
    use ApiResponse;

    protected function ensurePermission(string $permission): void
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }
        
        // super_admin and admin have all permissions
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return;
        }
        
        // For users with role_id (manager, staff, etc.), check role model permissions
        if ($user->role_id) {
            $role = $user->role()->first();
            if ($role && !$role->hasPermission($permission)) {
                abort(403, 'You do not have the required permission: ' . $permission);
            }
        } else {
            // If no role_id, deny access (shouldn't reach here for admin dashboard)
            abort(403, 'You do not have the required permission: ' . $permission);
        }
    }

    // ==================== DASHBOARD ====================

    public function dashboard(): JsonResponse
    {
        $this->ensurePermission('dashboard.view');
        
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('role', 'customer')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $recentOrders = Order::with('user')->latest()->limit(10)->get();

        // Growth calculations (vs previous month)
        $currentMonthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
        $previousMonthRevenue = Order::where('payment_status', 'paid')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total');
        $revenueGrowth = $previousMonthRevenue > 0
            ? round((($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100, 2)
            : 0;

        $currentMonthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $previousMonthOrders = Order::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $orderGrowth = $previousMonthOrders > 0
            ? round((($currentMonthOrders - $previousMonthOrders) / $previousMonthOrders) * 100, 2)
            : 0;

        // Top products by sales
        $topProducts = Product::with(['category', 'brand', 'images'])
            ->orderBy('sales_count', 'desc')
            ->limit(10)
            ->get();

        // Revenue chart (last 7 days)
        $revenueChart = collect();
        $ordersChart = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayRevenue = Order::where('payment_status', 'paid')
                ->whereDate('created_at', $date)
                ->sum('total');
            $dayOrders = Order::whereDate('created_at', $date)
                ->count();
            $revenueChart->push(['date' => $date, 'amount' => (float) $dayRevenue]);
            $ordersChart->push(['date' => $date, 'count' => $dayOrders]);
        }

        // Orders by status
        $ordersByStatus = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn($count, $status) => ['status' => $status, 'count' => $count])
            ->values()
            ->toArray();

        // Revenue by category (top 6)
        $revenueByCategory = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw('categories.name as category, sum(order_items.price * order_items.quantity) as revenue')
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->limit(6)
            ->pluck('revenue', 'category')
            ->map(fn($revenue, $cat) => ['category' => $cat, 'revenue' => (float) $revenue])
            ->values()
            ->toArray();

        return $this->success([
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'pending_orders' => $pendingOrders,
            'revenue_growth' => $revenueGrowth,
            'order_growth' => $orderGrowth,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'revenue_chart' => $revenueChart,
            'orders_chart' => $ordersChart,
            'orders_by_status' => $ordersByStatus,
            'revenue_by_category' => $revenueByCategory,
        ]);
    }

    // ==================== PRODUCTS ====================

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

    /**
     * Sync product colors and sizes as attributes
     */
    private function syncProductAttributes(Product $product, array $colors, array $sizes): void
    {
        // Get or create Color attribute
        $colorAttribute = Attribute::firstOrCreate(['name' => 'Color'], ['type' => 'color']);
        // Get or create Size attribute
        $sizeAttribute = Attribute::firstOrCreate(['name' => 'Size'], ['type' => 'size']);

        // Sync color values
        $colorValueIds = [];
        foreach ($colors as $color) {
            $colorValue = AttributeValue::firstOrCreate(
                ['attribute_id' => $colorAttribute->id, 'value' => $color],
                ['color_code' => null]
            );
            $colorValueIds[] = $colorValue->id;
        }

        // Sync size values
        $sizeValueIds = [];
        foreach ($sizes as $size) {
            $sizeValue = AttributeValue::firstOrCreate(
                ['attribute_id' => $sizeAttribute->id, 'value' => $size]
            );
            $sizeValueIds[] = $sizeValue->id;
        }

        // Sync product attributes (many-to-many)
        $allValueIds = array_merge($colorValueIds, $sizeValueIds);
        $product->attributes()->sync($allValueIds);
    }

    private function syncProductSpecifications(Product $product, array $specifications): void
    {
        // Delete existing specifications
        $product->specifications()->delete();

        // Create new specifications
        foreach ($specifications as $index => $spec) {
            if (isset($spec['name']) && isset($spec['value'])) {
                $product->specifications()->create([
                    'name' => $spec['name'],
                    'value' => $spec['value'],
                    'group' => $spec['group'] ?? null,
                    'sort_order' => $index,
                ]);
            }
        }
    }

    // ==================== CATEGORIES ====================

    public function getCategories(): JsonResponse
    {
        $this->ensurePermission('categories.view');
        
        $categories = Category::withCount('products')->latest()->get();
        return $this->success($categories);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:categories,slug',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($validated);
        return $this->success($category, 'Category created', 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:categories,slug,' . $id,
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($category->image);
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($validated);
        return $this->success($category->fresh(), 'Category updated');
    }

    public function toggleCategoryStatus(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);
        return $this->success($category->fresh(), 'Category status updated');
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }
        $category->delete();
        return $this->success(null, 'Category deleted');
    }

    // ==================== BRANDS ====================

    public function getBrands(): JsonResponse
    {
        $this->ensurePermission('brands.view');
        $brands = Brand::withCount('products')->latest()->get();
        return $this->success($brands);
    }

    public function createBrand(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:brands,slug',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand = Brand::create($validated);
        return $this->success($brand, 'Brand created', 201);
    }

    public function updateBrand(Request $request, int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:brands,slug,' . $id,
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
        ]);

        if ($request->hasFile('logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $validated['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($validated);
        return $this->success($brand->fresh(), 'Brand updated');
    }

    public function toggleBrandStatus(int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $brand->update(['is_active' => !$brand->is_active]);
        return $this->success($brand->fresh(), 'Brand status updated');
    }

    public function deleteBrand(int $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }
        $brand->delete();
        return $this->success(null, 'Brand deleted');
    }

    // ==================== ORDERS ====================

    public function getOrders(Request $request): JsonResponse
    {
        $this->ensurePermission('orders.view');
        $query = Order::with(['user', 'items.product.images', 'deliveryBoy', 'deliveryBooking.partnerInfo', 'shippingAddress', 'handler']);

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function lockOrder(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission('orders.view');
        $order = Order::findOrFail($id);

        if (!$order->handled_by) {
            $order->update(['handled_by' => auth()->id()]);
            $order->load('handler');
        } elseif ($order->handled_by !== auth()->id() && auth()->user()->role !== 'admin') {
            return $this->error('This order is already being handled by someone else', 403);
        }

        return $this->success($order, 'Order locked successfully');
    }

    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $this->ensurePermission('orders.update_status');
        
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,processing,shipped,delivered,cancelled,returned,return_requested,refunded',
            'payment_status' => 'sometimes|string|in:pending,paid,failed,refunded',
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;

        $updateData = ['status' => $validated['status']];
        

        // Update payment status if provided
        // if (
        //     isset($validated['payment_status']) &&
        //     $validated['payment_status'] === 'paid' &&
        //     $oldStatus !== 'paid' &&
        //     $order->total > 0
        // ) {

        //     $defaultAccount = AdminAccount::where('is_default', true)->first();

        //     if ($defaultAccount) {
        //         $newBalance = $defaultAccount->current_balance + $order->total;

        //         AdminAccountTransaction::create([
        //             'account_id' => $defaultAccount->id,
        //             'transaction_type' => 'order_payment',
        //             'amount' => $order->total,
        //             'balance_after' => $newBalance,
        //             'description' => 'Payment received for Order #' . ($order->order_number ?? $order->id),
        //             'reference_id' => $order->order_number ?? $order->id,
        //             'reference_type' => 'order',
        //             'transaction_date' => now(),
        //             'created_by' => auth()->id(),
        //         ]);

        //         $defaultAccount->update(['current_balance' => $newBalance]);
        //     }
        // }
        if (isset($validated['payment_status'])) {
            $updateData['payment_status'] = $validated['payment_status'];
        }

        // Only update timestamp columns that exist in the database
        // Note: shipped_at, delivered_at columns need to be added via migration
        if ($validated['status'] === 'shipped' && Schema::hasColumn('orders', 'shipped_at')) {
            $updateData['shipped_at'] = now();
        }

        if ($validated['status'] === 'delivered' && Schema::hasColumn('orders', 'delivered_at')) {
            $updateData['delivered_at'] = now();
        }

        if ($validated['status'] === 'return_requested' && Schema::hasColumn('orders', 'return_requested_at')) {
            $updateData['return_requested_at'] = now();
        }

        if ($validated['status'] === 'refunded') {
            $updateData['payment_status'] = 'refunded';
            if (Schema::hasColumn('orders', 'refunded_at')) {
                $updateData['refunded_at'] = now();
            }
        }

        $oldPaymentStatus = $order->payment_status;
        $order->update($updateData);

        // Award reward points when order is delivered and payment is successful
        // This should trigger if:
        // - Order just became 'delivered' AND payment is/was 'paid'
        // - Payment just became 'paid' AND order is already 'delivered'
        $wasAlreadyDeliveredAndPaid = ($oldStatus === 'delivered' && $oldPaymentStatus === 'paid');
        $isNowDeliveredAndPaid = ($order->status === 'delivered' && $order->payment_status === 'paid');

        if ($isNowDeliveredAndPaid && !$wasAlreadyDeliveredAndPaid) {
            $this->awardOrderPoints($order);
        }

        // Create admin account transaction when payment status changes to 'paid'
        // Only if it wasn't already paid
        if (
            isset($validated['payment_status']) &&
            $validated['payment_status'] === 'paid' &&
            $oldPaymentStatus !== 'paid' &&
            $order->total > 0
        ) {

            // Find the default admin account
            $defaultAccount = \App\Models\AdminAccount::where('is_default', true)->first();

            if ($defaultAccount) {
                // Calculate new balance
                $newBalance = $defaultAccount->current_balance + $order->total;

                // Create the transaction
                \App\Models\AdminAccountTransaction::create([
                    'account_id' => $defaultAccount->id,
                    'transaction_type' => 'order_payment',
                    'amount' => $order->total,
                    'balance_after' => $newBalance,
                    'description' => 'Payment received for Order #' . ($order->order_number ?? $order->id),
                    'reference_id' => $order->order_number ?? $order->id,
                    'reference_type' => 'order',
                    'transaction_date' => now(),
                    'created_by' => auth()->id(),
                ]);

                // Update account balance
                $defaultAccount->update(['current_balance' => $newBalance]);
            }
        }

        // Send status change notification
        app(\App\Services\NotificationService::class)->orderStatusChanged($order->fresh(), $oldStatus);

        return $this->success($order->fresh()->load('user'), 'Order status updated');
    }

    public function addTrackingNumber(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['tracking_number' => $validated['tracking_number']]);

        // Send shipping update notification
        app(\App\Services\NotificationService::class)->orderStatusChanged($order->fresh());

        return $this->success($order, 'Tracking number added');
    }

    // ==================== CUSTOMERS ====================

    public function getCustomers(Request $request): JsonResponse
    {
        $this->ensurePermission('customers.view');
        $query = User::where('role', 'customer');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function updateCustomerPassword(Request $request, $id): JsonResponse
    {
        $this->ensurePermission('customers.view'); // or appropriate permission

        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $customer = User::where('role', 'customer')->find($id);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $customer->password = Hash::make($request->password);
        $customer->save();

        return $this->success($customer, 'Customer password updated successfully');
    }

    public function impersonateCustomer($id): JsonResponse
    {
        $this->ensurePermission('customers.view'); // or appropriate permission

        $customer = User::where('role', 'customer')->find($id);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $customer
        ], 'Impersonation token generated');
    }

    // ==================== REPORTS ====================

    public function salesReport(Request $request): JsonResponse
    {
        return $this->report($request, 'sales');
    }

    public function report(Request $request, string $type): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';

        return match ($type) {
            'sales' => $this->success($this->salesReportData($start, $end)),
            'orders' => $this->success($this->ordersReportData($start, $end)),
            'products' => $this->success($this->productsReportData($start, $end)),
            'customers' => $this->success($this->customersReportData($start, $end)),
            'payments' => $this->success($this->paymentsReportData($start, $end)),
            'inventory' => $this->success($this->inventoryReportData()),
            default => $this->error('Invalid report type', 404),
        };
    }

    public function debug(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'authenticated' => !!$user,
            'user' => $user ? [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ] : null,
            'token' => $request->bearerToken() ? 'Present' : 'Missing',
        ]);
    }

    private function salesReportData(string $start, string $end): array
    {
        $paid = Order::where('payment_status', 'paid')->whereBetween('created_at', [$start, $end]);
        $all = Order::whereBetween('created_at', [$start, $end]);
        $daily = Order::selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return [
            'summary' => [
                'total_sales' => (float) (clone $paid)->sum('total'),
                'total_orders' => (clone $all)->count(),
                'paid_orders' => (clone $paid)->count(),
                'average_order' => (float) ((clone $paid)->avg('total') ?? 0),
                'discounts' => (float) (clone $all)->sum('discount'),
                'shipping' => (float) (clone $all)->sum('shipping_cost'),
                'tax' => (float) (clone $all)->sum('tax'),
            ],
            'columns' => ['date' => 'Date', 'orders' => 'Paid Orders', 'revenue' => 'Revenue'],
            'rows' => $daily->toArray(),
        ];
    }

    private function ordersReportData(string $start, string $end): array
    {
        $rows = Order::selectRaw('status, COUNT(*) as orders, SUM(total) as amount')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'summary' => [
                'total_orders' => (int) $rows->sum('orders'),
                'total_amount' => (float) $rows->sum('amount'),
                'delivered' => (int) ($rows->firstWhere('status', 'delivered')->orders ?? 0),
                'cancelled' => (int) ($rows->firstWhere('status', 'cancelled')->orders ?? 0),
            ],
            'columns' => ['status' => 'Status', 'orders' => 'Orders', 'amount' => 'Amount'],
            'rows' => $rows->toArray(),
        ];
    }

    private function productsReportData(string $start, string $end): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('products.id, products.name, products.sku, COALESCE(categories.name, "Uncategorized") as category, SUM(order_items.quantity) as quantity_sold, SUM(order_items.total) as revenue')
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(100)
            ->get();

        return [
            'summary' => [
                'products_sold' => (int) $rows->count(),
                'quantity_sold' => (int) $rows->sum('quantity_sold'),
                'revenue' => (float) $rows->sum('revenue'),
            ],
            'columns' => ['name' => 'Product', 'sku' => 'SKU', 'category' => 'Category', 'quantity_sold' => 'Qty Sold', 'revenue' => 'Revenue'],
            'rows' => $rows->toArray(),
        ];
    }

    private function customersReportData(string $start, string $end): array
    {
        $rows = User::query()
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->where('users.role', 'customer')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('users.id, users.name, users.email, COUNT(orders.id) as orders, SUM(orders.total) as spent, MAX(orders.created_at) as last_order_at')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('spent')
            ->limit(100)
            ->get();

        return [
            'summary' => [
                'customers' => (int) $rows->count(),
                'orders' => (int) $rows->sum('orders'),
                'spent' => (float) $rows->sum('spent'),
            ],
            'columns' => ['name' => 'Customer', 'email' => 'Email', 'orders' => 'Orders', 'spent' => 'Spent', 'last_order_at' => 'Last Order'],
            'rows' => $rows->toArray(),
        ];
    }

    private function paymentsReportData(string $start, string $end): array
    {
        $rows = Order::selectRaw('payment_method, payment_status, COUNT(*) as orders, SUM(total) as amount')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('payment_method', 'payment_status')
            ->orderBy('payment_method')
            ->get();

        return [
            'summary' => [
                'orders' => (int) $rows->sum('orders'),
                'amount' => (float) $rows->sum('amount'),
                'paid_amount' => (float) $rows->where('payment_status', 'paid')->sum('amount'),
            ],
            'columns' => ['payment_method' => 'Payment Method', 'payment_status' => 'Payment Status', 'orders' => 'Orders', 'amount' => 'Amount'],
            'rows' => $rows->toArray(),
        ];
    }

    private function inventoryReportData(): array
    {
        $rows = Product::with(['category:id,name'])
            ->select('id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold', 'price', 'category_id')
            ->orderBy('stock_quantity')
            ->limit(200)
            ->get()
            ->map(fn($product) => [
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name ?? 'Uncategorized',
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'stock_value' => (float) $product->stock_quantity * (float) $product->price,
            ]);

        return [
            'summary' => [
                'products' => (int) $rows->count(),
                'total_stock' => (int) $rows->sum('stock_quantity'),
                'low_stock' => (int) $rows->filter(fn($row) => $row['stock_quantity'] <= $row['low_stock_threshold'])->count(),
                'stock_value' => (float) $rows->sum('stock_value'),
            ],
            'columns' => ['name' => 'Product', 'sku' => 'SKU', 'category' => 'Category', 'stock_quantity' => 'Stock', 'low_stock_threshold' => 'Low Stock At', 'stock_value' => 'Stock Value'],
            'rows' => $rows->values(),
        ];
    }

    // ==================== SETTINGS ====================

    public function getSettings(): JsonResponse
    {
        $defaults = [
            'store_name' => config('app.name'),
            'currency' => 'BDT',
            'tax_rate' => 0,
            'shipping_cost_dhaka' => 60,
            'shipping_cost_outside' => 120,
            'topbar_phone' => '+880 1700-000000',
            'topbar_email' => 'info@shayanmart.com',
            'topbar_address' => 'Dhaka, Bangladesh',
            'topbar_show_phone' => true,
            'topbar_show_email' => true,
            'topbar_show_address' => true,
            'topbar_show_track_order' => true,
        ];

        $saved = cache()->get('admin_settings', []);
        return $this->success(array_merge($defaults, $saved));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->all();
        $existing = cache()->get('admin_settings', []);
        $merged = array_merge($existing, $data);
        cache()->set('admin_settings', $merged);
        return $this->success($merged, 'Settings updated');
    }

    // ==================== SHIPPING ZONES & METHODS ====================

    public function getShippingZones(Request $request): JsonResponse
    {
        $zones = ShippingZone::with(['methods' => function ($query) {
            $query->orderBy('sort_order')->where('is_active', true);
        }])->orderBy('sort_order')->get();

        return $this->success($zones);
    }

    public function getShippingZone(int $id): JsonResponse
    {
        $zone = ShippingZone::with(['methods' => function ($query) {
            $query->orderBy('sort_order');
        }])->findOrFail($id);

        return $this->success($zone);
    }

    public function createShippingZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'string',
            'states' => 'nullable|array',
            'states.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'string',
            'zip_codes' => 'nullable|array',
            'zip_codes.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'default_rate' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $zone = ShippingZone::create($validated);

        if (isset($validated['default_rate'])) {
            $zone->methods()->create([
                'name' => 'Standard Shipping',
                'description' => 'Default shipping method for this zone',
                'rate_type' => 'flat',
                'base_rate' => $validated['default_rate'],
                'estimated_days' => $validated['estimated_days'] ?? null,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        return $this->success($zone->load('methods'), 'Shipping zone created', 201);
    }

    public function updateShippingZone(Request $request, int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'string',
            'states' => 'nullable|array',
            'states.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'string',
            'zip_codes' => 'nullable|array',
            'zip_codes.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'default_rate' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
        ]);

        $zone->update($validated);

        if (isset($validated['default_rate']) || isset($validated['estimated_days'])) {
            $method = $zone->methods()->orderBy('sort_order')->first();

            if ($method) {
                $method->update(array_filter([
                    'base_rate' => $validated['default_rate'] ?? $method->base_rate,
                    'estimated_days' => $validated['estimated_days'] ?? $method->estimated_days,
                ], fn($value) => $value !== null));
            } elseif (isset($validated['default_rate'])) {
                $zone->methods()->create([
                    'name' => 'Standard Shipping',
                    'description' => 'Default shipping method for this zone',
                    'rate_type' => 'flat',
                    'base_rate' => $validated['default_rate'],
                    'estimated_days' => $validated['estimated_days'] ?? null,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }
        }

        return $this->success($zone->fresh()->load('methods'), 'Shipping zone updated');
    }

    public function deleteShippingZone(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->delete();
        return $this->success(null, 'Shipping zone deleted');
    }

    public function createShippingMethod(Request $request, int $zoneId): JsonResponse
    {
        $zone = ShippingZone::findOrFail($zoneId);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rate_type' => 'required|in:flat,weight_based,price_based,free',
            'base_rate' => 'required|numeric|min:0',
            'per_kg_rate' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_order_amount' => 'nullable|numeric|min:0',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['zone_id'] = $zone->id;

        $method = ShippingMethod::create($validated);
        return $this->success($method, 'Shipping method created', 201);
    }

    public function updateShippingMethod(Request $request, int $zoneId, int $methodId): JsonResponse
    {
        $zone = ShippingZone::findOrFail($zoneId);
        $method = $zone->methods()->findOrFail($methodId);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'rate_type' => 'sometimes|in:flat,weight_based,price_based,free',
            'base_rate' => 'sometimes|numeric|min:0',
            'per_kg_rate' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_order_amount' => 'nullable|numeric|min:0',
            'min_weight' => 'nullable|numeric|min:0',
            'max_weight' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $method->update($validated);
        return $this->success($method->fresh(), 'Shipping method updated');
    }

    public function deleteShippingMethod(int $zoneId, int $methodId): JsonResponse
    {
        $zone = ShippingZone::findOrFail($zoneId);
        $method = $zone->methods()->findOrFail($methodId);
        $method->delete();
        return $this->success(null, 'Shipping method deleted');
    }

    // ==================== COUPONS ====================

    public function getCoupons(): JsonResponse
    {
        $coupons = Coupon::with(['category:id,name', 'product:id,name'])->latest()->get();
        return $this->success($coupons);
    }

    public function createCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'usage_limit' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        if (!empty($validated['category_id']) && !empty($validated['product_id'])) {
            return $this->error('A coupon can be limited to a category or a product, not both.', 422);
        }

        $validated['is_active'] = $validated['is_active'] ?? true;
        $coupon = Coupon::create($validated);
        return $this->success($coupon->load(['category:id,name', 'product:id,name']), 'Coupon created', 201);
    }

    public function updateCoupon(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:coupons,code,' . $id,
            'type' => 'sometimes|string|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|integer|exists:categories,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'usage_limit' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        if (!empty($validated['category_id']) && !empty($validated['product_id'])) {
            return $this->error('A coupon can be limited to a category or a product, not both.', 422);
        }

        $coupon->update($validated);
        return $this->success($coupon->fresh()->load(['category:id,name', 'product:id,name']), 'Coupon updated');
    }

    public function deleteCoupon(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        return $this->success(null, 'Coupon deleted');
    }

    // ==================== REVIEWS ====================

    public function getReviews(Request $request): JsonResponse
    {
        $query = Review::with(['user', 'product']);

        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function deleteReview(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->delete();
        return $this->success(null, 'Review deleted');
    }

    // ==================== FAQs ====================

    public function getFAQs(): JsonResponse
    {
        $faqs = Faq::orderBy('sort_order')->latest()->get();
        return $this->success($faqs);
    }

    public function createFAQ(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'category' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $faq = Faq::create($validated);
        return $this->success($faq, 'FAQ created', 201);
    }

    public function updateFAQ(Request $request, int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $validated = $request->validate([
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
            'category' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $faq->update($validated);
        return $this->success($faq->fresh(), 'FAQ updated');
    }

    public function deleteFAQ(int $id): JsonResponse
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();
        return $this->success(null, 'FAQ deleted');
    }

    // ==================== BANNERS ====================

    public function getBanners(): JsonResponse
    {
        $banners = Banner::orderBy('sort_order')->latest()->get();
        return $this->success($banners);
    }

    public function createBanner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'link' => 'nullable|string',
            'position' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner = Banner::create($validated);
        return $this->success($banner, 'Banner created', 201);
    }

    public function updateBanner(Request $request, int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string',
            'link' => 'nullable|string',
            'position' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($validated);
        return $this->success($banner->fresh(), 'Banner updated');
    }

    public function deleteBanner(int $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }
        $banner->delete();
        return $this->success(null, 'Banner deleted');
    }

    // ==================== SLIDERS ====================

    public function getSliders(): JsonResponse
    {
        $sliders = Slider::orderBy('sort_order')->latest()->get();
        return $this->success($sliders);
    }

    public function createSlider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'description' => 'nullable|string',
            'link' => 'nullable|string',
            'button_text' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('sliders', 'public');
        }

        $slider = Slider::create($validated);
        return $this->success($slider, 'Slider created', 201);
    }

    public function updateSlider(Request $request, int $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string',
            'description' => 'nullable|string',
            'link' => 'nullable|string',
            'button_text' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,svg|max:4096',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            if ($slider->image) {
                Storage::disk('public')->delete($slider->image);
            }
            $validated['image'] = $request->file('image')->store('sliders', 'public');
        }

        $slider->update($validated);
        return $this->success($slider->fresh(), 'Slider updated');
    }

    public function deleteSlider(int $id): JsonResponse
    {
        $slider = Slider::findOrFail($id);
        if ($slider->image) {
            Storage::disk('public')->delete($slider->image);
        }
        $slider->delete();
        return $this->success(null, 'Slider deleted');
    }

    // ==================== BLOGS ====================

    public function getAdminBlogs(): JsonResponse
    {
        $blogs = Blog::with('author')->latest()->get();
        return $this->success($blogs);
    }

    public function createBlog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:blogs,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'category' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        // FormData sends booleans as strings ("true"/"false"), so cast manually
        $validated['is_published'] = filter_var($request->input('is_published', 'false'), FILTER_VALIDATE_BOOLEAN);
        $validated['author_id'] = auth()->id();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('blogs', 'public');
        }

        $blog = Blog::create($validated);
        return $this->success($blog, 'Blog created', 201);
    }

    public function updateBlog(Request $request, int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:blogs,slug,' . $id,
            'content' => 'sometimes|string',
            'excerpt' => 'nullable|string',
            'category' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        // FormData sends booleans as strings ("true"/"false"), so cast manually
        if ($request->has('is_published')) {
            $validated['is_published'] = filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('image')) {
            if ($blog->image) {
                Storage::disk('public')->delete($blog->image);
            }
            $validated['image'] = $request->file('image')->store('blogs', 'public');
        }

        $blog->update($validated);
        return $this->success($blog->fresh()->load('author'), 'Blog updated');
    }

    public function deleteBlog(int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);
        if ($blog->image) {
            Storage::disk('public')->delete($blog->image);
        }
        $blog->delete();
        return $this->success(null, 'Blog deleted');
    }

    // ==================== PAGES ====================

    public function getAdminPages(): JsonResponse
    {
        $pages = Page::latest()->get();
        return $this->success($pages);
    }

    public function createPage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:pages,slug',
            'content' => 'required|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $page = Page::create($validated);
        return $this->success($page, 'Page created', 201);
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:pages,slug,' . $id,
            'content' => 'sometimes|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $page->update($validated);
        return $this->success($page->fresh(), 'Page updated');
    }

    public function deletePage(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->delete();
        return $this->success(null, 'Page deleted');
    }

    // ==================== STAFF ====================

    public function getStaff(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $roleId = $request->get('role_id');
        $status = $request->get('status');

        $superAdminRoleIds = Role::where('slug', 'super_admin')->pluck('id')->toArray();

        $query = User::where('role', '!=', 'super_admin')
            ->where(function ($q) {
                $q->whereIn('role', ['admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'])
                  ->orWhere(function ($sub) {
                      $sub->whereNotIn('role', ['customer', 'vendor', 'delivery_boy', 'super_admin'])
                          ->whereNotNull('role_id');
                  });
            });

        if (!empty($superAdminRoleIds)) {
            $query->where(function ($q) use ($superAdminRoleIds) {
                $q->whereNotIn('role_id', $superAdminRoleIds)
                  ->orWhereNull('role_id');
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        if ($status !== null && $status !== '') {
            $isActive = filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $staff = $query->latest()->get();

        // Attach role info without breaking string enum `role`
        $roleIds = $staff->pluck('role_id')->filter()->unique();
        $rolesById = Role::whereIn('id', $roleIds)->get()->keyBy('id');

        $staff = $staff->filter(function ($user) use ($rolesById) {
            if ($user->role === 'super_admin') {
                return false;
            }
            if ($user->role_id && isset($rolesById[$user->role_id])) {
                if ($rolesById[$user->role_id]->slug === 'super_admin') {
                    return false;
                }
            }
            return true;
        })->values();

        $staff->transform(function ($user) use ($rolesById) {
            if ($user->role_id && isset($rolesById[$user->role_id])) {
                $user->role_name = $rolesById[$user->role_id]->name;
                $user->role_details = $rolesById[$user->role_id];
            } else {
                $user->role_name = ucfirst(str_replace('_', ' ', $user->role));
            }
            return $user;
        });

        return $this->success($staff);
    }

    public function createStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $roleSlug = 'staff';
        $roleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;

        if ($roleId) {
            $roleModel = Role::find($roleId);
            if ($roleModel) {
                $validEnumRoles = ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'];
                $roleSlug = in_array($roleModel->slug, $validEnumRoles) ? $roleModel->slug : 'staff';
            }
        } elseif (!empty($validated['role'])) {
            $roleSlug = $validated['role'];
            $roleModel = Role::where('slug', $roleSlug)->first();
            if ($roleModel) {
                $roleId = $roleModel->id;
            }
        } else {
            $defaultStaffRole = Role::where('slug', 'staff')->first();
            if ($defaultStaffRole) {
                $roleId = $defaultStaffRole->id;
                $roleSlug = 'staff';
            }
        }

        if ($roleSlug === 'super_admin') {
            return $this->error('Cannot assign Super Admin role to staff.', 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $roleSlug,
            'role_id' => $roleId,
            'is_active' => $validated['is_active'] ?? true,
            'is_verified' => true,
        ]);

        if ($roleId) {
            $roleModel = Role::find($roleId);
            $user->role_name = $roleModel ? $roleModel->name : ucfirst($roleSlug);
            $user->role_details = $roleModel;
        } else {
            $user->role_name = ucfirst(str_replace('_', ' ', $roleSlug));
        }

        return $this->success($user->makeHidden(['password']), 'Staff member created successfully', 201);
    }

    public function updateStaff(Request $request, int $id): JsonResponse
    {
        $staff = User::where('role', '!=', 'super_admin')
            ->where(function ($q) {
                $q->whereIn('role', ['admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'])
                  ->orWhereNotNull('role_id');
            })->findOrFail($id);

        if ($staff->role === 'super_admin') {
            return $this->error('Super Admin cannot be modified via staff management.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (array_key_exists('role_id', $validated)) {
            $roleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;
            $validated['role_id'] = $roleId;
            if ($roleId) {
                $roleModel = Role::find($roleId);
                if ($roleModel) {
                    $validEnumRoles = ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'];
                    $validated['role'] = in_array($roleModel->slug, $validEnumRoles) ? $roleModel->slug : 'staff';
                }
            }
        } elseif (!empty($validated['role'])) {
            $roleModel = Role::where('slug', $validated['role'])->first();
            if ($roleModel) {
                $validated['role_id'] = $roleModel->id;
            }
        }

        $staff->update($validated);

        if ($staff->role_id) {
            $roleModel = Role::find($staff->role_id);
            $staff->role_name = $roleModel ? $roleModel->name : ucfirst($staff->role);
            $staff->role_details = $roleModel;
        } else {
            $staff->role_name = ucfirst(str_replace('_', ' ', $staff->role));
        }

        return $this->success($staff->fresh()->makeHidden(['password']), 'Staff member updated successfully');
    }

    public function deleteStaff(int $id): JsonResponse
    {
        $staff = User::where(function ($q) {
            $q->whereIn('role', ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'])
              ->orWhereNotNull('role_id');
        })->findOrFail($id);

        if (auth()->id() === $staff->id) {
            return $this->error('You cannot delete your own account', 400);
        }

        if ($staff->role === 'super_admin') {
            return $this->error('Super Admin account cannot be deleted', 403);
        }

        $staff->delete();

        return $this->success(null, 'Staff member deleted successfully');
    }

    // ==================== RETURNS & REFUNDS ====================

    public function getReturns(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $query = Order::with(['user', 'items.product.images'])
            ->whereIn('status', ['return_requested', 'returned', 'refunded']);

        if ($status) {
            $query->where('status', $status);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function approveReturn(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'return_requested') {
            return $this->error('Only return_requested orders can be approved', 422);
        }

        $order->update([
            'status' => 'returned',
        ]);

        // Restore stock
        foreach ($order->items as $item) {
            \App\Models\Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
            \App\Models\Product::where('id', $item->product_id)->decrement('sales_count', $item->quantity);
        }

        return $this->success($order->fresh()->load(['user', 'items.product']), 'Return approved successfully');
    }

    public function rejectReturn(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'return_requested') {
            return $this->error('Only return_requested orders can be rejected', 422);
        }

        $order->update([
            'status' => 'delivered',
            'return_reason' => null,
            'return_requested_at' => null,
        ]);

        return $this->success($order->fresh()->load(['user', 'items.product']), 'Return request rejected');
    }

    public function processRefund(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0',
            'refund_reason' => 'required|string|max:500',
            'refund_method' => 'required|string|in:original_payment,bank_transfer,cash,store_credit,other',
        ]);

        $order = Order::findOrFail($id);

        if (!in_array($order->status, ['returned'])) {
            return $this->error('Only returned orders can be refunded', 422);
        }

        if ($validated['refund_amount'] > $order->total) {
            return $this->error('Refund amount cannot exceed order total', 422);
        }

        $order->update([
            'status' => 'refunded',
            'payment_status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $validated['refund_amount'],
            'refund_reason' => $validated['refund_reason'],
            'refund_method' => $validated['refund_method'],
        ]);

        return $this->success(
            $order->fresh()->load(['user', 'items.product']),
            'Refund processed successfully'
        );
    }

    public function getRefundDetails(int $id): JsonResponse
    {
        $order = Order::with(['user', 'items.product.images'])->findOrFail($id);

        if (!in_array($order->status, ['return_requested', 'returned', 'refunded'])) {
            return $this->error('Order is not in a return/refund state', 422);
        }

        return $this->success($order);
    }

    // ==================== POS (POINT OF SALE) ====================

    public function posSearchProducts(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $products = $query->limit(50)->get();
        return $this->success($products);
    }

    public function posCreateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string|in:cod,bkash,nagad,card,cash',
            'discount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Calculate totals
        $subtotal = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->stock_quantity < $item['quantity']) {
                return $this->error("Insufficient stock for {$product->name}. Available: {$product->stock_quantity}", 422);
            }

            $lineTotal = $product->price * $item['quantity'];
            $subtotal += $lineTotal;
            $orderItems[] = [
                'product_id' => $product->id,
                'price' => $product->price,
                'quantity' => $item['quantity'],
                'total' => $lineTotal,
            ];
        }

        $discount = $validated['discount'] ?? 0;
        $shippingCost = $validated['shipping_cost'] ?? 0;
        $tax = $validated['tax'] ?? 0;
        $total = $subtotal - $discount + $shippingCost + $tax;

        $userId = auth()->id();
        $orderNumber = 'POS-' . strtoupper(uniqid());

        $order = Order::create([
            'user_id' => $userId,
            'order_number' => $orderNumber,
            'status' => 'confirmed',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $total,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_method'] === 'cash' ? 'paid' : 'pending',
            'notes' => ($validated['notes'] ?? '') . " | POS Customer: " . ($validated['customer_name'] ?? 'Walk-in') . " | Phone: " . ($validated['customer_phone'] ?? 'N/A'),
        ]);

        // Create order items and decrement stock
        foreach ($orderItems as $oi) {
            $order->items()->create($oi);
            Product::where('id', $oi['product_id'])->decrement('stock_quantity', $oi['quantity']);
            Product::where('id', $oi['product_id'])->increment('sales_count', $oi['quantity']);
        }

        return $this->success($order->load('items.product'), 'POS order created successfully', 201);
    }

    // ==================== DELIVERY BOYS ====================

    public function getDeliveryBoys(Request $request): JsonResponse
    {
        $query = User::where('role', 'delivery_boy');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function createDeliveryBoy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['role'] = 'delivery_boy';
        $validated['password'] = bcrypt($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $deliveryBoy = User::create($validated);
        return $this->success($deliveryBoy->makeHidden(['password']), 'Delivery boy created', 201);
    }

    public function updateDeliveryBoy(Request $request, int $id): JsonResponse
    {
        $deliveryBoy = User::where('role', 'delivery_boy')->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $deliveryBoy->update($validated);
        return $this->success($deliveryBoy->makeHidden(['password']), 'Delivery boy updated');
    }

    public function deleteDeliveryBoy(int $id): JsonResponse
    {
        $deliveryBoy = User::where('role', 'delivery_boy')->findOrFail($id);

        // Unassign from any pending orders
        Order::where('delivery_boy_id', $id)
            ->whereIn('status', ['confirmed', 'processing'])
            ->update(['delivery_boy_id' => null, 'assigned_at' => null]);

        $deliveryBoy->delete();
        return $this->success(null, 'Delivery boy deleted');
    }

    // ==================== ORDER TRACKING ====================

    public function getOrderTracking(int $id): JsonResponse
    {
        $order = Order::with(['user', 'items.product', 'deliveryBoy', 'shippingAddress'])
            ->findOrFail($id);

        // Build timeline
        $timeline = [];
        $timeline[] = [
            'status' => 'Order Placed',
            'description' => 'Order was placed successfully',
            'timestamp' => $order->created_at,
            'completed' => true,
        ];

        if (in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered'])) {
            $timeline[] = [
                'status' => 'Confirmed',
                'description' => 'Order has been confirmed',
                'timestamp' => $order->updated_at,
                'completed' => true,
            ];
        }

        if ($order->assigned_at) {
            $timeline[] = [
                'status' => 'Assigned to Delivery',
                'description' => 'Assigned to ' . ($order->deliveryBoy?->name ?? 'Unknown'),
                'timestamp' => $order->assigned_at,
                'completed' => true,
            ];
        }

        if (in_array($order->status, ['processing', 'shipped', 'delivered'])) {
            $timeline[] = [
                'status' => 'Processing',
                'description' => 'Order is being processed',
                'timestamp' => $order->updated_at,
                'completed' => true,
            ];
        }

        if ($order->picked_up_at) {
            $timeline[] = [
                'status' => 'Picked Up',
                'description' => 'Package picked up by delivery boy',
                'timestamp' => $order->picked_up_at,
                'completed' => true,
            ];
        }

        if (in_array($order->status, ['shipped', 'delivered'])) {
            $timeline[] = [
                'status' => 'Shipped',
                'description' => 'Order has been shipped' . ($order->tracking_number ? " (Track: {$order->tracking_number})" : ''),
                'timestamp' => $order->shipped_at,
                'completed' => true,
            ];
        }

        if ($order->status === 'delivered') {
            $timeline[] = [
                'status' => 'Delivered',
                'description' => 'Order has been delivered successfully',
                'timestamp' => $order->delivered_at,
                'completed' => true,
            ];
        } elseif ($order->status === 'cancelled') {
            $timeline[] = [
                'status' => 'Cancelled',
                'description' => 'Order has been cancelled',
                'timestamp' => $order->updated_at,
                'completed' => true,
            ];
        } elseif ($order->status === 'returned') {
            $timeline[] = [
                'status' => 'Returned',
                'description' => 'Order has been returned',
                'timestamp' => $order->updated_at,
                'completed' => true,
            ];
        } else {
            if ($order->status !== 'shipped') {
                $timeline[] = [
                    'status' => 'Out for Delivery',
                    'description' => 'Waiting for pickup',
                    'timestamp' => null,
                    'completed' => false,
                ];
            }
            if ($order->status !== 'delivered') {
                $timeline[] = [
                    'status' => 'Delivered',
                    'description' => 'Awaiting delivery confirmation',
                    'timestamp' => null,
                    'completed' => false,
                ];
            }
        }

        return $this->success([
            'order' => $order,
            'timeline' => $timeline,
        ]);
    }

    public function assignDeliveryBoy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'delivery_boy_id' => 'required|exists:users,id',
        ]);

        $order = Order::findOrFail($id);
        $deliveryBoy = User::where('id', $validated['delivery_boy_id'])
            ->where('role', 'delivery_boy')
            ->firstOrFail();

        $order->update([
            'delivery_boy_id' => $deliveryBoy->id,
            'assigned_at' => now(),
            'status' => 'processing',
        ]);

        return $this->success(
            $order->fresh()->load(['user', 'deliveryBoy']),
            'Delivery boy assigned successfully'
        );
    }

    public function updateOrderTracking(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,confirmed,processing,shipped,delivered,cancelled,returned',
            'tracking_number' => 'nullable|string',
            'delivery_notes' => 'nullable|string',
            'picked_up_at' => 'nullable|date',
        ]);

        $order = Order::findOrFail($id);

        if (isset($validated['status'])) {
            $order->update(['status' => $validated['status']]);
            if ($validated['status'] === 'shipped' && !$order->shipped_at && Schema::hasColumn('orders', 'shipped_at')) {
                $order->update(['shipped_at' => now()]);
            }
            if ($validated['status'] === 'delivered' && !$order->delivered_at && Schema::hasColumn('orders', 'delivered_at')) {
                $order->update(['delivered_at' => now()]);
            }
        }

        if (isset($validated['tracking_number'])) {
            $order->update(['tracking_number' => $validated['tracking_number']]);
        }

        if (isset($validated['delivery_notes'])) {
            $order->update(['delivery_notes' => $validated['delivery_notes']]);
        }

        if (isset($validated['picked_up_at'])) {
            $order->update(['picked_up_at' => $validated['picked_up_at']]);
        }

        return $this->success(
            $order->fresh()->load(['user', 'items.product', 'deliveryBoy']),
            'Order tracking updated'
        );
    }

    // ==================== NOTIFICATIONS ====================

    public function getNotifications(Request $request): JsonResponse
    {
        $query = NotificationModel::with('user:id,name,email,avatar');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($type = $request->type) {
            $query->where('type', $type);
        }

        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($userId = $request->user_id) {
            $query->where('user_id', $userId);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'sometimes|string|in:info,success,warning,error',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $notification = $user->notifications_model()->create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'info',
        ]);

        return $this->success($notification, 'Notification sent', 201);
    }

    public function sendBulkNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'sometimes|string|in:info,success,warning,error',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = $user->notifications_model()->create([
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'] ?? 'info',
            ]);
        }

        return $this->success($notifications, count($notifications) . ' notifications sent', 201);
    }

    public function markNotificationRead($id): JsonResponse
    {
        $notification = NotificationModel::findOrFail($id);
        $notification->update(['is_read' => true]);
        return $this->success($notification, 'Notification marked as read');
    }

    public function markAllRead(): JsonResponse
    {
        NotificationModel::where('is_read', false)->update(['is_read' => true]);
        return $this->success(null, 'All notifications marked as read');
    }

    public function deleteNotification(int $id): JsonResponse
    {
        $notification = NotificationModel::findOrFail($id);
        $notification->delete();
        return $this->success(null, 'Notification deleted');
    }

    public function notificationStats(): JsonResponse
    {
        $total = NotificationModel::count();
        $unread = NotificationModel::where('is_read', false)->count();
        $today = NotificationModel::whereDate('created_at', today())->count();

        return $this->success([
            'total' => $total,
            'unread' => $unread,
            'today' => $today,
        ]);
    }

    // ==================== PAYMENT GATEWAYS ====================

    public function getPaymentGateways(): JsonResponse
    {
        $gateways = PaymentGateway::orderBy('sort_order')->get();
        return $this->success($gateways);
    }

    public function updatePaymentGateway(Request $request, int $id): JsonResponse
    {
        $gateway = PaymentGateway::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,test',
            'test_mode' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $gateway->update($validated);
        return $this->success($gateway->fresh(), 'Payment gateway updated');
    }

    // ==================== PAYMENT TRANSACTIONS ====================

    public function getPaymentTransactions(Request $request): JsonResponse
    {
        $query = PaymentTransaction::with('order:id,user_id,total,status');

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%")
                    ->orWhere('gateway', 'like', "%{$search}%");
            });
        }

        if ($gateway = $request->gateway) {
            $query->where('gateway', $gateway);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    // ==================== VENDOR MANAGEMENT ====================

    public function getVendors(Request $request): JsonResponse
    {
        $query = VendorShop::with(['user:id,name,email,phone,is_active,created_at']);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('shop_name', 'like', "%{$search}%")
                    ->orWhere('shop_slug', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->status) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'verified') {
                $query->where('is_verified', true);
            } elseif ($status === 'unverified') {
                $query->where('is_verified', false);
            }
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function getVendor(int $id): JsonResponse
    {
        $vendor = VendorShop::with([
            'user:id,name,email,phone,is_active,created_at',
            'products:id,name,slug,price,stock_quantity,is_active,vendor_id',
            'payouts:id,vendor_shop_id,amount,status,created_at',
        ])->findOrFail($id);

        return $this->success($vendor);
    }

    public function updateVendor(Request $request, int $id): JsonResponse
    {
        $vendor = VendorShop::findOrFail($id);
        $validated = $request->validate([
            'shop_name' => 'sometimes|string|max:255',
            'shop_slug' => 'sometimes|string|max:255|unique:vendor_shops,shop_slug,' . $id,
            'shop_description' => 'nullable|string',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'business_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
        ]);

        $vendor->update($validated);
        return $this->success($vendor->fresh(['user']), 'Vendor updated');
    }

    public function deleteVendor(int $id): JsonResponse
    {
        $vendor = VendorShop::findOrFail($id);
        $vendor->delete();
        return $this->success(null, 'Vendor deleted');
    }

    public function vendorStats(): JsonResponse
    {
        $total = VendorShop::count();
        $active = VendorShop::where('is_active', true)->count();
        $verified = VendorShop::where('is_verified', true)->count();
        $pendingVerification = VendorShop::where('is_verified', false)->count();
        $totalRevenue = VendorShop::sum('total_revenue');
        $totalEarnings = VendorShop::sum('total_earnings');
        $pendingPayout = VendorShop::sum('pending_payout');

        return $this->success([
            'total' => $total,
            'active' => $active,
            'verified' => $verified,
            'pending_verification' => $pendingVerification,
            'total_revenue' => (float) $totalRevenue,
            'total_earnings' => (float) $totalEarnings,
            'pending_payout' => (float) $pendingPayout,
        ]);
    }

    // ==================== DELIVERY PARTNERS ====================

    public function getDeliveryPartners(Request $request): JsonResponse
    {
        $query = DeliveryPartner::query();

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $result = $query->withCount('bookings')->latest()->get();
        return $this->success($result);
    }

    public function getDeliveryPartner(int $id): JsonResponse
    {
        $partner = DeliveryPartner::withCount('bookings')->findOrFail($id);
        return $this->success($partner);
    }

    public function createDeliveryPartner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:delivery_partners,slug',
            'description' => 'nullable|string',
            'logo' => 'nullable|string|max:500',
            'config' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
            'is_sandbox' => 'sometimes|boolean',
            'supported_areas' => 'nullable|array',
            'supported_service_types' => 'nullable|array',
            'cod_enabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $partner = DeliveryPartner::create($validated);
        return $this->success($partner, 'Delivery partner created', 201);
    }

    public function updateDeliveryPartner(Request $request, int $id): JsonResponse
    {
        $partner = DeliveryPartner::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|unique:delivery_partners,slug,' . $id,
            'description' => 'nullable|string',
            'logo' => 'nullable|string|max:500',
            'config' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
            'is_sandbox' => 'sometimes|boolean',
            'supported_areas' => 'nullable|array',
            'supported_service_types' => 'nullable|array',
            'cod_enabled' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $partner->update($validated);
        return $this->success($partner, 'Delivery partner updated');
    }

    public function deleteDeliveryPartner(int $id): JsonResponse
    {
        $partner = DeliveryPartner::findOrFail($id);
        $partner->delete();
        return $this->success(null, 'Delivery partner deleted');
    }

    /**
     * Customer overview / courier fraud check by phone number.
     *
     * Returns this store's own order history for the phone plus each requested
     * courier partner's fraud-check overview (delivered vs cancelled parcels).
     */
    public function getCustomerCourierOverview(Request $request, CourierFraudChecker $checker): JsonResponse
    {
        $validated = $request->validate([
            'phone'         => 'required|string|max:30',
            'partner'       => 'nullable|string',
            'partner_id'    => 'nullable|integer|exists:delivery_partners,id',
            'refresh'       => 'nullable',
            'force_refresh' => 'nullable',
        ]);

        $phone = trim($validated['phone']);
        $refresh = $request->boolean('force_refresh') || $request->boolean('refresh');

        // Which partners to query: a specific one if given, otherwise all active partners.
        if (!empty($validated['partner'])) {
            $partners = DeliveryPartner::where('slug', $validated['partner'])->get();
        } elseif (!empty($validated['partner_id'])) {
            $partners = DeliveryPartner::where('id', $validated['partner_id'])->get();
        } else {
            $partners = DeliveryPartner::where('status', 'active')
                ->orWhere('is_active', true)
                ->get();
        }

        $couriers = $partners->map(fn(DeliveryPartner $p) => $checker->partnerOverview($p, $phone, $refresh))->values();

        return $this->success([
            'phone'    => $phone,
            'local'    => $checker->localOverview($phone),
            'couriers' => $couriers,
        ]);
    }

    public function getDeliveryBookings(Request $request): JsonResponse
    {
        $query = DeliveryBooking::with('order');

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($partner = $request->partner) {
            $query->where('partner', $partner);
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_id', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%");
            });
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    public function bookDelivery(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['shippingAddress', 'user'])->findOrFail($orderId);

        $validated = $request->validate([
            'partner' => 'nullable|string',
            'partner_id' => 'nullable|integer|exists:delivery_partners,id',
            'pickup_address' => 'nullable|string',
            'service_type' => 'nullable|string',
        ]);

        // Find partner by slug or id
        $partner = null;
        if (!empty($validated['partner'])) {
            $partner = DeliveryPartner::where('slug', $validated['partner'])->first();
        }
        if (!$partner && !empty($validated['partner_id'])) {
            $partner = DeliveryPartner::find($validated['partner_id']);
        }
        if (!$partner) {
            return $this->error('Delivery partner not found.', 422);
        }

        $shippingAddress = $order->shippingAddress;
        $deliveryAddress = $shippingAddress
            ? trim(($shippingAddress->address_line_1 ?? '') . ', ' . ($shippingAddress->city ?? '') . ', ' . ($shippingAddress->state ?? '') . ' ' . ($shippingAddress->postal_code ?? ''), ', ')
            : 'N/A';
        if (strlen($deliveryAddress) < 10) {
            $deliveryAddress .= ', ' . str_repeat(' ', 10 - strlen($deliveryAddress)) . 'Address info';
        }

        $pickupAddress = $validated['pickup_address'] ?? 'Warehouse / Shop';
        
        $trackingId = strtoupper(uniqid($partner->slug . '-'));
        $status = 'pending';
        
        // Clean phone number (keep only digits, ensure 11 length for BD)
        $rawPhone = $shippingAddress->phone ?? $order->user->phone ?? '01700000000';
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (strlen($cleanPhone) > 11 && str_starts_with($cleanPhone, '8801')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        if (strlen($cleanPhone) < 11) {
            $cleanPhone = str_pad($cleanPhone, 11, '0', STR_PAD_RIGHT);
        }

        try {
            if ($partner->slug === 'steadfast') {
                $steadfast = app(\App\Services\SteadfastCourierService::class);
                $res = $steadfast->createOrder([
                    'invoice' => $order->order_number,
                    'recipient_name' => $order->user->name ?? 'N/A',
                    'recipient_phone' => $cleanPhone,
                    'recipient_address' => $deliveryAddress,
                    'cod_amount' => $order->payment_status !== 'paid' ? (int)round($order->total) : 0,
                    'note' => $order->notes ?? '',
                ]);
                if (isset($res['consignment_id'])) {
                    $trackingId = $res['consignment_id'];
                }
            } elseif ($partner->slug === 'pathao') {
                $pathao = app(\App\Services\PathaoCourierService::class);
                
                // Fetch the actual store_id from Pathao
                $stores = $pathao->getStores();
                $storeId = $stores['data']['data'][0]['store_id'] ?? $partner->config['merchant_id'] ?? 0;

                // We pass standard item_type 2 (parcel), delivery_type 48 (normal) etc.
                $res = $pathao->createOrder([
                    'store_id' => $storeId,
                    'merchant_order_id' => $order->order_number,
                    'recipient_name' => $order->user->name ?? 'N/A',
                    'recipient_phone' => $cleanPhone,
                    'recipient_address' => $deliveryAddress,
                    'delivery_type' => 48, // 48 is Normal Delivery typically
                    'item_type' => 2, // 2 is Parcel
                    'item_quantity' => 1,
                    'item_weight' => 0.5,
                    'amount_to_collect' => $order->payment_status !== 'paid' ? (int)round($order->total) : 0,
                ]);
                if (isset($res['data']['consignment_id'])) {
                    $trackingId = $res['data']['consignment_id'];
                } else {
                    throw new \Exception(json_encode($res));
                }
            }
        } catch (\Exception $e) {
            // Log it but continue with local dummy tracking if the API fails or just fail
            // It's usually better to fail so the admin knows Pathao rejected it.
            return $this->error('Failed to book with ' . ucfirst($partner->slug) . ': ' . $e->getMessage(), 500);
        }

        $booking = DeliveryBooking::create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'partner' => $partner->slug,
            'tracking_id' => $trackingId,
            'status' => $status,
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'recipient_name' => $order->user->name ?? 'N/A',
            'recipient_phone' => $shippingAddress->phone ?? $order->user->phone ?? '',
            'cod_amount' => $order->payment_method === 'cod' ? $order->total : null,
            'shipping_fee' => $order->shipping_cost ?? 0,
            'estimated_delivery' => now()->addDays(3)->toDateString(),
            'booked_at' => now(),
        ]);

        // Update order with tracking info and status
        $order->update([
            'tracking_number' => $booking->tracking_id,
            'status' => 'processing',
            'assigned_at' => $order->assigned_at ?? now(),
        ]);

        return $this->success($booking->load('order'), 'Delivery booked successfully', 201);
    }

    public function updateDeliveryBooking(Request $request, int $id): JsonResponse
    {
        $booking = DeliveryBooking::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,picked,in_transit,delivered,returned,failed',
            'tracking_id' => 'sometimes|string',
            'picked_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
        ]);

        $booking->update($validated);

        // If delivered, update order status
        if (isset($validated['status']) && $validated['status'] === 'delivered') {
            $order = Order::find($booking->order_id);
            if ($order && !in_array($order->status, ['delivered', 'cancelled'])) {
                $updateData = ['status' => 'delivered'];
                if (Schema::hasColumn('orders', 'delivered_at')) {
                    $updateData['delivered_at'] = $validated['delivered_at'] ?? now();
                }
                $order->update($updateData);
            }
        }

        return $this->success($booking->load('order'), 'Booking updated');
    }

    // ==================== FLASH SALES ====================

    public function getFlashSales(): JsonResponse
    {
        $sales = FlashSale::withCount('products')
            ->with(['products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price');
            }])
            ->orderBy('sort_order')
            ->get();
        return $this->success($sales);
    }

    public function createFlashSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'exists:products,id',
            'flash_prices' => 'sometimes|array',
            'flash_prices.*' => 'nullable|numeric|min:0',
        ]);

        $productIds = $validated['product_ids'] ?? [];
        $flashPrices = $validated['flash_prices'] ?? [];
        unset($validated['product_ids'], $validated['flash_prices']);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $sale = FlashSale::create($validated);

        foreach ($productIds as $index => $productId) {
            $sale->products()->attach($productId, [
                'flash_price' => $flashPrices[$index] ?? null,
            ]);
        }

        $sale->loadCount('products')->load(['products' => function ($q) {
            $q->select('products.id', 'products.name', 'products.price');
        }]);

        return $this->success($sale, 'Flash sale created');
    }

    public function updateFlashSale(Request $request, int $id): JsonResponse
    {
        $sale = FlashSale::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'exists:products,id',
            'flash_prices' => 'sometimes|array',
            'flash_prices.*' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('product_ids', $validated)) {
            $productIds = $validated['product_ids'] ?? [];
            $flashPrices = $validated['flash_prices'] ?? [];
            unset($validated['product_ids'], $validated['flash_prices']);

            $sale->products()->detach();
            foreach ($productIds as $index => $productId) {
                $sale->products()->attach($productId, [
                    'flash_price' => $flashPrices[$index] ?? null,
                ]);
            }
        } else {
            unset($validated['product_ids'], $validated['flash_prices']);
        }

        $sale->update($validated);
        $sale->loadCount('products')->load(['products' => function ($q) {
            $q->select('products.id', 'products.name', 'products.price');
        }]);

        return $this->success($sale, 'Flash sale updated');
    }

    public function deleteFlashSale(int $id): JsonResponse
    {
        $sale = FlashSale::findOrFail($id);
        $sale->products()->detach();
        $sale->delete();
        return $this->success(null, 'Flash sale deleted');
    }

    // ==================== CAMPAIGNS ====================

    public function getCampaigns(): JsonResponse
    {
        $campaigns = Campaign::orderByDesc('created_at')->get();
        return $this->success($campaigns);
    }

    public function createCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:email,sms,social,promotion,banner',
            'status' => 'sometimes|in:draft,scheduled,active,paused,completed,cancelled',
            'coupon_code' => 'nullable|string|max:50',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'budget' => 'nullable|numeric|min:0',
            'target_audience' => 'sometimes|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $campaign = Campaign::create($validated);

        if ($request->hasFile('image')) {
            $campaign->update(['image' => $request->file('image')->store('campaigns', 'public')]);
        }

        return $this->success($campaign, 'Campaign created');
    }

    public function updateCampaign(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:email,sms,social,promotion,banner',
            'status' => 'sometimes|in:draft,scheduled,active,paused,completed,cancelled',
            'coupon_code' => 'nullable|string|max:50',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'budget' => 'nullable|numeric|min:0',
            'target_audience' => 'sometimes|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $campaign->update($validated);

        if ($request->hasFile('image')) {
            $campaign->update(['image' => $request->file('image')->store('campaigns', 'public')]);
        }

        return $this->success($campaign, 'Campaign updated');
    }

    public function deleteCampaign(int $id): JsonResponse
    {
        Campaign::findOrFail($id)->delete();
        return $this->success(null, 'Campaign deleted');
    }

    // ==================== SUBSCRIBERS ====================

    public function getSubscribers(Request $request): JsonResponse
    {
        $query = Subscriber::with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $subscribers = $query->orderByDesc('subscribed_at');
        $result = $this->paginated($subscribers);
        return $this->success($result);
    }

    public function getSubscriberStats(): JsonResponse
    {
        $total = Subscriber::count();
        $active = Subscriber::where('status', 'active')->count();
        $unsubscribed = Subscriber::where('status', 'unsubscribed')->count();
        $bounced = Subscriber::where('status', 'bounced')->count();
        $recent = Subscriber::where('subscribed_at', '>=', now()->subDays(30))->count();

        return $this->success(compact('total', 'active', 'unsubscribed', 'bounced', 'recent'));
    }

    public function updateSubscriber(Request $request, int $id): JsonResponse
    {
        $subscriber = Subscriber::findOrFail($id);
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,unsubscribed,bounced',
        ]);

        $subscriber->update($validated);
        return $this->success($subscriber, 'Subscriber updated');
    }

    public function deleteSubscriber(int $id): JsonResponse
    {
        Subscriber::findOrFail($id)->delete();
        return $this->success(null, 'Subscriber deleted');
    }

    public function exportSubscribers(): JsonResponse
    {
        $subscribers = Subscriber::where('status', 'active')
            ->select('email', 'name', 'source', 'subscribed_at')
            ->get();

        return $this->success($subscribers);
    }

    // ==================== SUPPORT TICKETS ====================

    public function supportTickets(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['user', 'replies', 'replies.user'])
            ->withCount('replies')
            ->latest();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Filter by department
        if ($request->has('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        // Search by ticket number or subject
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $result = $this->paginated($query);
        return $this->success($result, 'Support tickets retrieved');
    }

    public function getSupportTicket(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(['user', 'replies', 'replies.user'])->findOrFail($id);
        return $this->success($ticket, 'Support ticket retrieved');
    }

    public function replyToSupportTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'status' => 'sometimes|string|in:open,replied,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $oldStatus = $ticket->status;

        // Update ticket status if provided
        if (isset($validated['status'])) {
            $ticket->update(['status' => $validated['status']]);
        }

        // Create admin reply
        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => true,
        ]);

        // Auto-update status to replied if admin replies and status wasn't explicitly set
        if (!isset($validated['status'])) {
            $ticket->update(['status' => 'replied']);
        }

        // Notify customer about the admin's reply
        app(NotificationService::class)->supportTicketRepliedByAdmin($ticket->load('user'), $validated['message']);

        return $this->success(
            $ticket->fresh()->load(['user', 'replies', 'replies.user']),
            'Reply sent successfully'
        );
    }

    public function updateSupportTicketStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,replied,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $oldStatus = $ticket->status;
        $ticket->update(['status' => $validated['status']]);

        // Notify customer about status change
        app(NotificationService::class)->supportTicketStatusUpdated($ticket->load('user'), $oldStatus);

        return $this->success($ticket->fresh(), 'Ticket status updated');
    }

    public function deleteSupportTicket(int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $ticket->delete();

        return $this->success(null, 'Ticket deleted successfully');
    }

    public function supportTicketStats(): JsonResponse
    {
        $stats = [
            'open' => SupportTicket::where('status', 'open')->count(),
            'replied' => SupportTicket::where('status', 'replied')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'high' => SupportTicket::where('priority', 'high')->whereIn('status', ['open', 'replied'])->count(),
            'medium' => SupportTicket::where('priority', 'medium')->whereIn('status', ['open', 'replied'])->count(),
            'low' => SupportTicket::where('priority', 'low')->whereIn('status', ['open', 'replied'])->count(),
        ];

        return $this->success($stats, 'Support ticket statistics');
    }

    // ==================== REWARD POINTS ====================

    /**
     * Award reward points for an order when it's marked as delivered.
     */
    private function awardOrderPoints(Order $order): void
    {
        if (!$order->user || $order->payment_status !== 'paid') {
            return;
        }

        try {
            $service = app(RewardPointService::class);
            $service->awardPoints($order->user, $order);
        } catch (\RuntimeException $e) {
            // Log but don't fail the order update if points can't be awarded
            \Log::warning("Failed to award points for order #{$order->order_number}: " . $e->getMessage());
        }
    }

    public function getRewardPoints(Request $request): JsonResponse
    {
        $service = app(RewardPointService::class);
        $filters = $request->only(['user_id', 'type', 'search', 'expired']);
        $perPage = $request->get('per_page', 15);

        $result = $service->getAllRewardPoints($filters, $perPage);

        return $this->success($result);
    }

    public function getRewardPointStats(): JsonResponse
    {
        $service = app(RewardPointService::class);
        $stats = $service->getStats();

        return $this->success($stats);
    }

    public function getRewardPointSettings(): JsonResponse
    {
        $service = app(RewardPointService::class);
        $settings = $service->getSettings();

        return $this->success($settings);
    }

    public function updateRewardPointSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'points_rate' => 'required|integer|min:1',
            'expiry_days' => 'required|integer|min:0',
            'min_order' => 'required|numeric|min:0',
            'max_per_order' => 'required|integer|min:1',
            'redemption_rate' => 'required|integer|min:1',
        ]);

        $service = app(RewardPointService::class);
        $settings = $service->updateSettings($validated);

        return $this->success($settings, 'Reward points settings updated');
    }

    public function adjustUserPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'points' => 'required|integer',
            'type' => 'required|in:earned,redeemed',
            'description' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $service = app(RewardPointService::class);

        $rewardPoint = $service->adjustBalance(
            $user,
            $validated['points'],
            $validated['type'],
            $validated['description']
        );

        return $this->success($rewardPoint, 'Points adjusted successfully', 201);
    }

    public function expireOldPoints(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $service = app(RewardPointService::class);
        $user = $validated['user_id'] ? User::findOrFail($validated['user_id']) : null;
        $count = $service->expireOldPoints($user);

        return $this->success(['expired_count' => $count], 'Old points expired successfully');
    }
    public function updateReviewStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['is_approved' => 'required|boolean']);
        $review = Review::findOrFail($id);
        $review->update(['is_approved' => $request->boolean('is_approved')]);

        // Recalculate product rating & count for approved reviews
        if ($review->product_id) {
            $product = Product::find($review->product_id);
            if ($product) {
                $approvedReviews = $product->reviews()->where('is_approved', true);
                $avgRating = $approvedReviews->avg('rating') ?: 0;
                $product->update([
                    'average_rating' => round($avgRating, 1),
                    'reviews_count' => $approvedReviews->count(),
                ]);
            }
        }

        return $this->success($review, 'Review status updated');
    }
}
