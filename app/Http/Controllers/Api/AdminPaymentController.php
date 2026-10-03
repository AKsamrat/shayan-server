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

class AdminPaymentController extends AdminController
{
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

}
