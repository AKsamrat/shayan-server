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
class AdminReturnController extends AdminController
{
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

    public function getRefundDetails(int $id): JsonResponse
    {
        $order = Order::with(['user', 'items.product.images'])->findOrFail($id);

        if (!in_array($order->status, ['return_requested', 'returned', 'refunded'])) {
            return $this->error('Order is not in a return/refund state', 422);
        }

        return $this->success($order);
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

}

