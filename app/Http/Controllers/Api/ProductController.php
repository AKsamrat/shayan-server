<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\FlashSale;
use App\Models\Review;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true);

        // Search
        if ($search = $request->q) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->category) {
            $query->where('category_id', $category);
        }

        // Brand filter
        if ($brand = $request->brand) {
            $query->where('brand_id', $brand);
        }

        // Price range
        if ($minPrice = $request->min_price) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice = $request->max_price) {
            $query->where('price', '<=', $maxPrice);
        }

        // Featured / Flash Sale / Best Seller
        if ($request->boolean('is_featured')) $query->where('is_featured', true);
        if ($request->boolean('is_flash_sale')) $query->where('is_flash_sale', true);
        if ($request->boolean('is_best_seller')) $query->where('is_best_seller', true);

        // Rating filter
        if ($minRating = $request->min_rating) {
            $query->where('average_rating', '>=', $minRating);
        }

        // In stock
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['name', 'price', 'average_rating', 'created_at', 'sales_count'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir);
        }

        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['category', 'brand', 'images', 'variants.attributeValues.attribute', 'attributes.values'])
            ->where('slug', $slug)
            ->firstOrFail();

        $product->increment('views_count');

        // Load related products from same category
        $product->related_products = Product::with(['category', 'brand', 'images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(8)
            ->get();

        // Load frequently bought together (top selling products from same category)
        $product->frequently_bought_together = Product::with(['category', 'brand', 'images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->orderBy('sales_count', 'desc')
            ->limit(4)
            ->get();

        return $this->success($product);
    }

    public function featured(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->limit(10)
            ->get();

        return $this->success($products);
    }

    public function newArrivals(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $this->success($products);
    }

    public function flashSale(): JsonResponse
    {
        $now = now();

        // Active admin-managed flash-sale campaigns (see Admin → Flash Sales).
        // A campaign is live when it's toggled active AND the current time falls
        // within its start/end window. Ordered by the admin's sort_order.
        $sales = FlashSale::where('is_active', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('sort_order')
            ->with(['products' => function ($q) {
                $q->where('products.is_active', true)
                    ->with(['category', 'brand', 'images']);
            }])
            ->get();

        // Flatten the products across every live campaign, applying each
        // campaign's flash price. We surface the sale price through the standard
        // price/compare_price fields so ProductCard renders the discount with no
        // changes — these mutations are in-memory only (never persisted).
        $products = collect();
        foreach ($sales as $sale) {
            foreach ($sale->products as $product) {
                $original = (float) $product->price;
                $flashPrice = $product->pivot->flash_price !== null
                    ? (float) $product->pivot->flash_price
                    : round($original * (1 - ((float) $sale->discount_percentage) / 100), 2);

                $product->compare_price = $original;
                $product->price = $flashPrice;
                $product->is_flash_sale = true;
                unset($product->pivot);

                $products->push($product);
            }
        }

        // A product may appear in more than one live campaign — keep the cheapest.
        $products = $products->groupBy('id')
            ->map(fn($group) => $group->sortBy('price')->first())
            ->values();

        // The storefront countdown tracks the soonest-ending live campaign.
        $endsAt = $sales->pluck('end_date')->filter()->sort()->first();

        return $this->success([
            'products' => $products,
            'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
        ]);
    }

    public function bestSellers(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('is_best_seller', true)
            ->orderBy('sales_count', 'desc')
            ->limit(10)
            ->get();

        return $this->success($products);
    }

    public function related(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $related = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->limit(8)
            ->get();

        return $this->success($related);
    }

    public function frequentlyBought(int $id): JsonResponse
    {
        // Products bought together (simplified: same category)
        $product = Product::findOrFail($id);
        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->inRandomOrder()
            ->limit(6)
            ->get();

        return $this->success($products);
    }

    public function reviews(Request $request, int $id): JsonResponse
    {
        $query = Review::with('user')->where('product_id', $id)->where('is_approved', true)->latest();
        $result = $this->paginated($query);
        return $this->success($result);
    }

    public function addReview(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string',
        ]);

        $user = $request->user();

        // Check if user has purchased this product and order is delivered
        $hasDeliveredOrder = \App\Models\Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', function ($q) use ($id) {
                $q->where('product_id', $id);
            })->exists();

        if (!$hasDeliveredOrder) {
            return $this->error('You can only review products you have purchased and received.', 403);
        }

        // Check if user already reviewed this product
        $existingReview = \App\Models\Review::where('user_id', $user->id)
            ->where('product_id', $id)
            ->first();

        if ($existingReview) {
            return $this->error('You have already submitted a review for this product.', 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $id,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'comment' => $validated['comment'],
            'is_approved' => false,
            'is_verified_purchase' => true,
        ]);

        return $this->success($review->load('user'), 'Review submitted successfully! It will appear once approved by an admin.', 201);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->q;
        if (!$query) {
            return $this->error('Search query is required', 422);
        }

        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('tags', 'like', "%{$query}%");
            });

        $result = $this->paginated($products);
        return $this->success($result);
    }
}
