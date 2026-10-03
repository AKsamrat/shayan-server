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
class AdminDashboardController extends AdminController
{
    public function dashboard(): JsonResponse
    {
        $this->ensurePermission('dashboard.view');
        
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('role', 'customer')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $recentOrders = Order::with('user')->latest()->limit(10)->get();

        $deliveredOrders = Order::where('status', 'delivered')->count();
        $productQty = Product::sum('stock_quantity') ?? 0;
        $stockProductValue = Product::selectRaw('sum(price * stock_quantity) as total_value')->value('total_value') ?? 0;
        $bookedSteadfast = DeliveryBooking::where('partner', 'steadfast')->count();
        $bookedPathao = DeliveryBooking::where('partner', 'pathao')->count();
        $returnQty = Order::where('status', 'returned')->count();

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
            'delivered_orders' => $deliveredOrders,
            'product_qty' => (int) $productQty,
            'stock_product_value' => (float) $stockProductValue,
            'booked_steadfast' => $bookedSteadfast,
            'booked_pathao' => $bookedPathao,
            'return_qty' => $returnQty,
        ]);
    }

}

