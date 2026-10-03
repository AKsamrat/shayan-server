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
class AdminCouponController extends AdminController
{
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

}

