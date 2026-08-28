<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find or create cart by user_id
        // For guests, find or create cart by session_id
        $cart = Cart::with(['items.product.images', 'items.variant.attributeValues.attribute'])
            ->firstOrCreate($user ? ['user_id' => $user->id] : ['session_id' => $sessionId]);

        return $this->success($cart);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'variant_id' => 'sometimes|nullable|exists:product_variants,id',
        ]);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find or create cart by user_id
        // For guests, find or create cart by session_id
        $cart = Cart::firstOrCreate($user ? ['user_id' => $user->id] : ['session_id' => $sessionId]);

        $existingItem = $cart->items()
            ->where('product_id', $validated['product_id'])
            ->where('variant_id', $validated['variant_id'] ?? null)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $validated['quantity'] ?? 1);
        } else {
            $product = Product::find($validated['product_id']);
            $variant = isset($validated['variant_id']) ? $product->variants()->find($validated['variant_id']) : null;
            $price = $variant ? $variant->price : $product->price;

            $cart->items()->create([
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'] ?? 1,
                'price' => $price,
            ]);
        }

        return $this->success(
            $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']),
            'Item added to cart'
        );
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find cart by user_id
        // For guests, find cart by session_id
        $cart = Cart::where($user ? 'user_id' : 'session_id', $user ? $user->id : $sessionId)->firstOrFail();
        $item = $cart->items()->findOrFail($itemId);
        $item->update(['quantity' => $validated['quantity']]);

        return $this->success(
            $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']),
            'Cart updated'
        );
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find cart by user_id
        // For guests, find cart by session_id
        $cart = Cart::where($user ? 'user_id' : 'session_id', $user ? $user->id : $sessionId)->firstOrFail();
        $cart->items()->where('id', $itemId)->delete();

        return $this->success(
            $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']),
            'Item removed from cart'
        );
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $coupon = Coupon::where('code', $validated['code'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$coupon) {
            return $this->error('Invalid or expired coupon code', 422);
        }

        if ($coupon->used_count >= $coupon->usage_limit) {
            return $this->error('Coupon usage limit reached', 422);
        }

        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find cart by user_id
        // For guests, find cart by session_id
        $cart = Cart::with('items.product:id,category_id,sub_category_id,child_category_id')
            ->where($user ? 'user_id' : 'session_id', $user ? $user->id : $sessionId)
            ->firstOrFail();

        if ($cart->items->isEmpty()) {
            return $this->error('Your cart is empty', 422);
        }

        // Scoped coupons: the cart must contain a qualifying item.
        if ($coupon->product_id) {
            $hasProduct = $cart->items->contains(
                fn ($item) => (int) $item->product_id === (int) $coupon->product_id
            );
            if (!$hasProduct) {
                return $this->error('This coupon only applies to a specific product that is not in your cart.', 422);
            }
        }

        if ($coupon->category_id) {
            $hasCategory = $cart->items->contains(function ($item) use ($coupon) {
                $product = $item->product;
                if (!$product) {
                    return false;
                }
                $categoryIds = array_filter([
                    $product->category_id,
                    $product->sub_category_id,
                    $product->child_category_id,
                ]);
                return in_array((int) $coupon->category_id, array_map('intval', $categoryIds), true);
            });
            if (!$hasCategory) {
                return $this->error('This coupon only applies to a specific category not represented in your cart.', 422);
            }
        }

        // Store coupon in session or cart metadata
        $cart->update(['coupon_code' => $coupon->code]);

        return $this->success(
            $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']),
            'Coupon applied successfully'
        );
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find cart by user_id
        // For guests, find cart by session_id
        $cart = Cart::where($user ? 'user_id' : 'session_id', $user ? $user->id : $sessionId)->firstOrFail();
        $cart->update(['coupon_code' => null]);

        return $this->success(
            $cart->fresh(['items.product.images', 'items.variant.attributeValues.attribute']),
            'Coupon removed'
        );
    }

    public function calculateShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'postal_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionId = $request->session()->getId();

        // For authenticated users, find cart by user_id
        // For guests, find cart by session_id
        $cart = Cart::with(['items.product', 'items.variant'])
            ->where($user ? 'user_id' : 'session_id', $user ? $user->id : $sessionId)
            ->firstOrFail();

        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->variant ? $item->variant->price : $item->product->price;
            return $price * $item->quantity;
        });

        $matchedZone = ShippingZone::where('is_active', true)
            ->with(['methods' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get()
            ->first(function ($zone) use ($validated) {
                $countryMatch = empty($zone->countries) || in_array($validated['country'], $zone->countries, true);
                $stateMatch = empty($zone->states) || in_array($validated['state'], $zone->states, true);
                $cityMatch = empty($zone->cities) || in_array($validated['city'], $zone->cities, true);
                $zipMatch = empty($zone->zip_codes) || empty($validated['postal_code']) || in_array($validated['postal_code'], $zone->zip_codes, true);

                return $countryMatch && $stateMatch && $cityMatch && $zipMatch;
            });

        $cost = null;
        $estimatedDays = null;

        if ($matchedZone && $matchedZone->methods->isNotEmpty()) {
            $method = $matchedZone->methods->first();
            switch ($method->rate_type) {
                case 'free':
                    $cost = 0.0;
                    break;
                case 'price_based':
                    $minOk = $method->min_order_amount === null || $subtotal >= $method->min_order_amount;
                    $maxOk = $method->max_order_amount === null || $subtotal <= $method->max_order_amount;
                    $cost = $minOk && $maxOk ? $method->base_rate : null;
                    break;
                case 'weight_based':
                    $cost = $method->base_rate;
                    break;
                default:
                    $cost = $method->base_rate;
            }
            $estimatedDays = $method->estimated_days;
        }

        if ($cost === null) {
            $isDhaka = str_contains(strtolower($validated['city']), 'dhaka') || str_contains(strtolower($validated['state']), 'dhaka');
            $cost = $isDhaka ? 60.00 : 120.00;
            $estimatedDays = $isDhaka ? '2-3 business days' : '4-7 business days';
        }

        return $this->success([
            'cost' => $cost,
            'estimated_days' => $estimatedDays,
        ]);
    }

    /**
     * Public shipping estimation endpoint.
     * Returns all matching zones with methods and calculated costs.
     * No authentication required.
     */
    public function estimateShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city'        => 'required|string',
            'state'       => 'required|string',
            'country'     => 'required|string',
            'postal_code' => 'nullable|string',
            'subtotal'    => 'nullable|numeric|min:0',
        ]);

        $subtotal = (float) ($validated['subtotal'] ?? 0);

        // Get all active zones with their active methods
        $zones = ShippingZone::where('is_active', true)
            ->with(['methods' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Find all matching zones
        $matchingZones = $zones->filter(function ($zone) use ($validated) {
            $countryMatch = empty($zone->countries) || in_array($validated['country'], $zone->countries, true);
            $stateMatch   = empty($zone->states)   || in_array($validated['state'], $zone->states, true);
            $cityMatch    = empty($zone->cities)    || in_array($validated['city'], $zone->cities, true);
            $zipMatch     = empty($zone->zip_codes) || empty($validated['postal_code']) || in_array($validated['postal_code'], $zone->zip_codes, true);

            return $countryMatch && $stateMatch && $cityMatch && $zipMatch;
        });

        $results = [];

        foreach ($matchingZones as $zone) {
            foreach ($zone->methods as $method) {
                $cost = null;

                switch ($method->rate_type) {
                    case 'free':
                        $cost = 0.0;
                        break;
                    case 'price_based':
                        $minOk = $method->min_order_amount === null || $subtotal >= (float) $method->min_order_amount;
                        $maxOk = $method->max_order_amount === null || $subtotal <= (float) $method->max_order_amount;
                        $cost = $minOk && $maxOk ? (float) $method->base_rate : null;
                        break;
                    case 'weight_based':
                        $cost = (float) $method->base_rate;
                        break;
                    default: // flat
                        $cost = (float) $method->base_rate;
                        break;
                }

                $results[] = [
                    'zone_id'        => $zone->id,
                    'zone_name'      => $zone->name,
                    'method_id'      => $method->id,
                    'name'           => $method->name,
                    'description'    => $method->description,
                    'rate_type'      => $method->rate_type,
                    'base_rate'      => (float) $method->base_rate,
                    'per_kg_rate'    => $method->per_kg_rate !== null ? (float) $method->per_kg_rate : null,
                    'min_order_amount' => $method->min_order_amount !== null ? (float) $method->min_order_amount : null,
                    'max_order_amount' => $method->max_order_amount !== null ? (float) $method->max_order_amount : null,
                    'estimated_days' => $method->estimated_days,
                    'cost'           => $cost,
                ];
            }
        }

        // Fallback: if no zones matched, provide default Dhaka/outside rates
        if (empty($results)) {
            $isDhaka = str_contains(strtolower($validated['city']), 'dhaka')
                || str_contains(strtolower($validated['state']), 'dhaka');

            $results[] = [
                'zone_id'          => null,
                'zone_name'        => 'Default',
                'method_id'        => null,
                'name'             => $isDhaka ? 'Standard Delivery (Dhaka)' : 'Standard Delivery (Outside Dhaka)',
                'description'      => 'Default shipping rate',
                'rate_type'        => 'flat',
                'base_rate'        => $isDhaka ? 60.0 : 120.0,
                'per_kg_rate'      => null,
                'min_order_amount' => null,
                'max_order_amount' => null,
                'estimated_days'   => $isDhaka ? '2-3 business days' : '4-7 business days',
                'cost'             => $isDhaka ? 60.0 : 120.0,
            ];
        }

        return $this->success([
            'methods' => $results,
            'city'    => $validated['city'],
            'state'   => $validated['state'],
            'country' => $validated['country'],
        ]);
    }

    /**
     * Public list of active delivery zones, each with a single representative
     * delivery cost taken from the zone's primary (first active) shipping method.
     * Powers the "Delivery Area" selector on the checkout page. No auth required.
     */
    public function publicZones(): JsonResponse
    {
        $zones = ShippingZone::where('is_active', true)
            ->with(['methods' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $results = $zones->map(function ($zone) {
            $method = $zone->methods->first();

            if (! $method) {
                return null;
            }

            $cost = $method->rate_type === 'free' ? 0.0 : (float) $method->base_rate;

            return [
                'id'             => $zone->id,
                'name'           => $zone->name,
                'description'    => $zone->description,
                'cost'           => $cost,
                'method_id'      => $method->id,
                'method_name'    => $method->name,
                'estimated_days' => $method->estimated_days,
            ];
        })->filter()->values();

        return $this->success($results);
    }
}
