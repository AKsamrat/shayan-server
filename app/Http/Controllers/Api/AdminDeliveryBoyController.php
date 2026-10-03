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
class AdminDeliveryBoyController extends AdminController
{
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

}

