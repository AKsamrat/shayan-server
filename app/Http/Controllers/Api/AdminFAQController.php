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

class AdminFAQController extends AdminController
{
    public function getFAQs(): JsonResponse
        {
            $faqs = Faq::orderBy('sort_order')->latest()->get();
            return $this->success($faqs);
        }

    public function createFAQ(Request $request): JsonResponse
        {
            $validated = $request->validate([
                'question' => 'required|string',
                'answer' => 'required|string',
                'category' => 'nullable|string',
                'sort_order' => 'nullable|integer',
            ]);

            $faq = Faq::create($validated);
            return $this->success($faq, 'FAQ created', 201);
        }

    public function updateFAQ(Request $request, int $id): JsonResponse
        {
            $faq = Faq::findOrFail($id);
            $validated = $request->validate([
                'question' => 'sometimes|string',
                'answer' => 'sometimes|string',
                'category' => 'nullable|string',
                'sort_order' => 'nullable|integer',
            ]);

            $faq->update($validated);
            return $this->success($faq->fresh(), 'FAQ updated');
        }

    public function deleteFAQ(int $id): JsonResponse
        {
            $faq = Faq::findOrFail($id);
            $faq->delete();
            return $this->success(null, 'FAQ deleted');
        }

}
