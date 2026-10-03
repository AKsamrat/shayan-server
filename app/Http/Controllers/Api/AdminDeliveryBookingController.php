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
class AdminDeliveryBookingController extends AdminController
{
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

}

