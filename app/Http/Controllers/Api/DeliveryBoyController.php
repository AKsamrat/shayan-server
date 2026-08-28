<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeliveryBoyController extends Controller
{
    use ApiResponse;

    /**
     * Delivery boy dashboard stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $boy = $request->user();

        $assignedOrders = Order::where('delivery_boy_id', $boy->id)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->count();

        $pendingOrders = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'processing')
            ->count();

        $inTransitOrders = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'shipped')
            ->count();

        $deliveredOrders = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->count();

        $todayDeliveries = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        $todayEarnings = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->sum('delivery_fee');

        $totalEarnings = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->sum('delivery_fee');

        $recentOrders = Order::with(['user', 'items.product'])
            ->where('delivery_boy_id', $boy->id)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'stats' => [
                'assigned_orders' => $assignedOrders,
                'pending_orders' => $pendingOrders,
                'in_transit_orders' => $inTransitOrders,
                'delivered_orders' => $deliveredOrders,
                'today_deliveries' => $todayDeliveries,
                'today_earnings' => $todayEarnings,
                'total_earnings' => $totalEarnings,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * List orders assigned to the delivery boy
     */
    public function myOrders(Request $request): JsonResponse
    {
        $boy = $request->user();
        $status = $request->status;

        $query = Order::with(['user', 'items.product.images', 'shippingAddress'])
            ->where('delivery_boy_id', $boy->id)
            ->whereNotIn('status', ['cancelled', 'returned']);

        if ($status) {
            $query->where('status', $status);
        }

        $result = $this->paginated($query->latest());
        return $this->success($result);
    }

    /**
     * Get single order details
     */
    public function orderDetail(Request $request, int $id): JsonResponse
    {
        $boy = $request->user();

        $order = Order::with(['user', 'items.product.images', 'shippingAddress'])
            ->where('id', $id)
            ->where('delivery_boy_id', $boy->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found or not assigned to you.', 404);
        }

        return $this->success($order);
    }

    /**
     * Update delivery status (pickup, in-transit, delivered)
     */
    public function updateDeliveryStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:shipped,delivered',
        ]);

        $boy = $request->user();

        $order = Order::where('id', $id)
            ->where('delivery_boy_id', $boy->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found or not assigned to you.', 404);
        }

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'shipped') {
            $updateData['picked_up_at'] = now();
            if (!$order->shipped_at) {
                $updateData['shipped_at'] = now();
            }
        }

        if ($validated['status'] === 'delivered') {
            $updateData['delivered_at'] = now();
        }

        $order->update($updateData);

        return $this->success(
            $order->fresh()->load(['user', 'items.product']),
            'Order status updated successfully'
        );
    }

    /**
     * Get delivery boy profile
     */
    public function profile(Request $request): JsonResponse
    {
        $boy = $request->user();

        $totalDeliveries = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->count();

        $totalEarnings = Order::where('delivery_boy_id', $boy->id)
            ->where('status', 'delivered')
            ->sum('delivery_fee');

        return $this->success([
            'user' => $boy,
            'stats' => [
                'total_deliveries' => $totalDeliveries,
                'total_earnings' => $totalEarnings,
            ],
        ]);
    }

    /**
     * Update delivery boy profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|min:8|confirmed',
        ]);

        $boy = $request->user();

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $boy->update($validated);

        return $this->success($boy->fresh(), 'Profile updated successfully');
    }
}
