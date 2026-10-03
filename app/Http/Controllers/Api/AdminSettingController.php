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

class AdminSettingController extends AdminController
{
    public function getSettings(): JsonResponse
        {
            $defaults = [
                'store_name' => config('app.name'),
                'currency' => 'BDT',
                'tax_rate' => 0,
                'shipping_cost_dhaka' => 60,
                'shipping_cost_outside' => 120,
                'topbar_phone' => '+880 1700-000000',
                'topbar_email' => 'info@shayanmart.com',
                'topbar_address' => 'Dhaka, Bangladesh',
                'topbar_show_phone' => true,
                'topbar_show_email' => true,
                'topbar_show_address' => true,
                'topbar_show_track_order' => true,
            ];

            $saved = cache()->get('admin_settings', []);
            return $this->success(array_merge($defaults, $saved));
        }

    public function updateSettings(Request $request): JsonResponse
        {
            $data = $request->all();
            $existing = cache()->get('admin_settings', []);
            $merged = array_merge($existing, $data);
            cache()->set('admin_settings', $merged);
            return $this->success($merged, 'Settings updated');
        }

}
