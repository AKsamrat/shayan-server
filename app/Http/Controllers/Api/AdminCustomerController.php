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
class AdminCustomerController extends AdminController
{
    public function updateCustomerPassword(Request $request, $id): JsonResponse
    {
        $this->ensurePermission('customers.view'); // or appropriate permission

        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $customer = User::where('role', 'customer')->find($id);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $customer->password = Hash::make($request->password);
        $customer->save();

        return $this->success($customer, 'Customer password updated successfully');
    }

    public function impersonateCustomer($id): JsonResponse
    {
        $this->ensurePermission('customers.view'); // or appropriate permission

        $customer = User::where('role', 'customer')->find($id);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $customer
        ], 'Impersonation token generated');
    }

}

