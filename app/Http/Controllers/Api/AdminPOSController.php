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
class AdminPOSController extends AdminController
{
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

}

