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
class AdminOrderController extends AdminController
{
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

}

