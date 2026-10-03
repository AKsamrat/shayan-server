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
class AdminFlashSaleController extends AdminController
{
    public function getFlashSales(): JsonResponse
    {
        $sales = FlashSale::withCount('products')
            ->with(['products' => function ($q) {
                $q->select('products.id', 'products.name', 'products.price');
            }])
            ->orderBy('sort_order')
            ->get();
        return $this->success($sales);
    }

    public function createFlashSale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'exists:products,id',
            'flash_prices' => 'sometimes|array',
            'flash_prices.*' => 'nullable|numeric|min:0',
        ]);

        $productIds = $validated['product_ids'] ?? [];
        $flashPrices = $validated['flash_prices'] ?? [];
        unset($validated['product_ids'], $validated['flash_prices']);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $sale = FlashSale::create($validated);

        foreach ($productIds as $index => $productId) {
            $sale->products()->attach($productId, [
                'flash_price' => $flashPrices[$index] ?? null,
            ]);
        }

        $sale->loadCount('products')->load(['products' => function ($q) {
            $q->select('products.id', 'products.name', 'products.price');
        }]);

        return $this->success($sale, 'Flash sale created');
    }

    public function updateFlashSale(Request $request, int $id): JsonResponse
    {
        $sale = FlashSale::findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'discount_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'exists:products,id',
            'flash_prices' => 'sometimes|array',
            'flash_prices.*' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('product_ids', $validated)) {
            $productIds = $validated['product_ids'] ?? [];
            $flashPrices = $validated['flash_prices'] ?? [];
            unset($validated['product_ids'], $validated['flash_prices']);

            $sale->products()->detach();
            foreach ($productIds as $index => $productId) {
                $sale->products()->attach($productId, [
                    'flash_price' => $flashPrices[$index] ?? null,
                ]);
            }
        } else {
            unset($validated['product_ids'], $validated['flash_prices']);
        }

        $sale->update($validated);
        $sale->loadCount('products')->load(['products' => function ($q) {
            $q->select('products.id', 'products.name', 'products.price');
        }]);

        return $this->success($sale, 'Flash sale updated');
    }

    public function deleteFlashSale(int $id): JsonResponse
    {
        $sale = FlashSale::findOrFail($id);
        $sale->products()->detach();
        $sale->delete();
        return $this->success(null, 'Flash sale deleted');
    }

}

