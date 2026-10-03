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
class AdminShippingZoneController extends AdminController
{
    public function getShippingZone(int $id): JsonResponse
    {
        $zone = ShippingZone::with(['methods' => function ($query) {
            $query->orderBy('sort_order');
        }])->findOrFail($id);

        return $this->success($zone);
    }

    public function createShippingZone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'string',
            'states' => 'nullable|array',
            'states.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'string',
            'zip_codes' => 'nullable|array',
            'zip_codes.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'default_rate' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $zone = ShippingZone::create($validated);

        if (isset($validated['default_rate'])) {
            $zone->methods()->create([
                'name' => 'Standard Shipping',
                'description' => 'Default shipping method for this zone',
                'rate_type' => 'flat',
                'base_rate' => $validated['default_rate'],
                'estimated_days' => $validated['estimated_days'] ?? null,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        return $this->success($zone->load('methods'), 'Shipping zone created', 201);
    }

    public function updateShippingZone(Request $request, int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'countries' => 'nullable|array',
            'countries.*' => 'string',
            'states' => 'nullable|array',
            'states.*' => 'string',
            'cities' => 'nullable|array',
            'cities.*' => 'string',
            'zip_codes' => 'nullable|array',
            'zip_codes.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'default_rate' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:255',
        ]);

        $zone->update($validated);

        if (isset($validated['default_rate']) || isset($validated['estimated_days'])) {
            $method = $zone->methods()->orderBy('sort_order')->first();

            if ($method) {
                $method->update(array_filter([
                    'base_rate' => $validated['default_rate'] ?? $method->base_rate,
                    'estimated_days' => $validated['estimated_days'] ?? $method->estimated_days,
                ], fn($value) => $value !== null));
            } elseif (isset($validated['default_rate'])) {
                $zone->methods()->create([
                    'name' => 'Standard Shipping',
                    'description' => 'Default shipping method for this zone',
                    'rate_type' => 'flat',
                    'base_rate' => $validated['default_rate'],
                    'estimated_days' => $validated['estimated_days'] ?? null,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }
        }

        return $this->success($zone->fresh()->load('methods'), 'Shipping zone updated');
    }

    public function deleteShippingZone(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->delete();
        return $this->success(null, 'Shipping zone deleted');
    }

}

