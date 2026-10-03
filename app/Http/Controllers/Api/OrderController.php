<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'shippingAddress'])
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address_id' => 'required|exists:addresses,id',
            'billing_address_id' => 'nullable|exists:addresses,id',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
            'coupon_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        // Require authentication for placing orders
        if (!$user) {
            return $this->error('Authentication required to place an order', 401);
        }

        // Find cart by user_id first, then fall back to session_id
        // This handles the case where user added items as guest then logged in
        $cart = Cart::with(['items.product', 'items.variant'])
            ->where('user_id', $user->id)
            ->orWhere('session_id', $sessionId)
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return $this->error('Cart is empty', 422);
        }

        // Calculate totals
        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->variant ? $item->variant->price : $item->product->price;
            return $price * $item->quantity;
        });

        $discountAmount = 0;
        if ($cart->coupon_code) {
            $coupon = Coupon::where('code', $cart->coupon_code)->first();
            if ($coupon) {
                if ($coupon->type === 'percentage') {
                    $discountAmount = ($subtotal * $coupon->value) / 100;
                    if ($coupon->maximum_discount && $discountAmount > $coupon->maximum_discount) {
                        $discountAmount = $coupon->maximum_discount;
                    }
                } else {
                    $discountAmount = min($coupon->value, $subtotal);
                }
                $coupon->increment('used_count');
            }
        }

        $shippingCost = 60.00;
        $taxAmount = 0;
        $totalAmount = $subtotal - $discountAmount + $shippingCost + $taxAmount;

        // Auto-generate tracking number: TRK-XXXXXXXX (8 random uppercase chars)
        $trackingNumber = 'TRK-' . strtoupper(Str::random(8));

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'tracking_number' => $trackingNumber,
            'subtotal' => $subtotal,
            'discount' => $discountAmount,
            'shipping_cost' => $shippingCost,
            'tax' => $taxAmount,
            'total' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $validated['payment_method'],
            'shipping_address_id' => $validated['shipping_address_id'],
            'billing_address_id' => $validated['billing_address_id'] ?? $validated['shipping_address_id'],
            'notes' => $validated['notes'] ?? null,
            'shipping_method' => $request->input('shipping_method'),
            'ip_address' => $request->input('ip_address', $request->ip()),
            'mac_address' => $request->input('mac_address'),
        ]);

        // Create order items
        foreach ($cart->items as $item) {
            $price = $item->variant ? $item->variant->price : $item->product->price;
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'price' => $price,
                'quantity' => $item->quantity,
                'total' => $price * $item->quantity,
            ]);

            // Decrease stock
            Product::where('id', $item->product_id)->decrement('stock_quantity', $item->quantity);
            Product::where('id', $item->product_id)->increment('sales_count', $item->quantity);
        }

        // Clear cart
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);

        // Send order placed notification (in-app + email + SMS based on user preferences)
        app(NotificationService::class)->orderPlaced($order);

        return $this->success(
            $order->load(['items', 'shippingAddress']),
            'Order placed successfully',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'items.variant', 'shippingAddress', 'billingAddress'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return $this->success($order);
    }

    public function track(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $tracking = [
            ['date' => $order->created_at->toDateTimeString(), 'status' => 'Order Placed', 'location' => 'Online'],
        ];

        if ($order->status !== 'pending') {
            $tracking[] = ['date' => $order->created_at->addHours(2)->toDateTimeString(), 'status' => 'Processing', 'location' => 'Warehouse'];
        }
        if (in_array($order->status, ['shipped', 'delivered'])) {
            $tracking[] = ['date' => $order->shipped_at?->toDateTimeString() ?? now()->toDateTimeString(), 'status' => 'Shipped', 'location' => 'Distribution Center'];
        }
        if ($order->status === 'delivered') {
            $tracking[] = ['date' => $order->delivered_at?->toDateTimeString() ?? now()->toDateTimeString(), 'status' => 'Delivered', 'location' => 'Customer Address'];
        }

        return $this->success([
            'status' => $order->status,
            'tracking' => $tracking,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return $this->error('Order cannot be cancelled at this stage', 422);
        }

        $order->update(['status' => 'cancelled', 'payment_status' => 'failed']);

        // Restore stock
        foreach ($order->items as $item) {
            Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
            Product::where('id', $item->product_id)->decrement('sales_count', $item->quantity);
        }

        return $this->success($order, 'Order cancelled successfully');
    }

    public function requestReturn(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($order->status, ['delivered'])) {
            return $this->error('Only delivered orders can be returned', 422);
        }

        // Admin-configurable refund policy (group "refund").
        $policy = Setting::getGroup('refund');

        if (array_key_exists('refund_enabled', $policy) && !$policy['refund_enabled']) {
            return $this->error('Returns are currently not being accepted.', 422);
        }

        $windowDays = (int) ($policy['return_window_days'] ?? 7);
        if ($windowDays > 0) {
            // The live orders table has no delivered_at column, so fall back to
            // when the order last changed (i.e. moved to "delivered").
            $deliveredAt = $order->delivered_at ?? $order->updated_at;
            if ($deliveredAt && $deliveredAt->lt(now()->subDays($windowDays))) {
                return $this->error(
                    "The {$windowDays}-day return window for this order has passed.",
                    422
                );
            }
        }

        // Persist only to columns that actually exist in the live schema
        // (return_reason / return_requested_at may be absent — see schema drift).
        $update = ['status' => 'return_requested'];
        if (Schema::hasColumn('orders', 'return_reason')) {
            $update['return_reason'] = $validated['reason'];
        } else {
            $note = 'Return requested: ' . $validated['reason'];
            $update['notes'] = $order->notes ? $order->notes . "\n" . $note : $note;
        }
        if (Schema::hasColumn('orders', 'return_requested_at')) {
            $update['return_requested_at'] = now();
        }
        $order->update($update);

        return $this->success($order->fresh(), 'Return request submitted successfully');
    }
}
