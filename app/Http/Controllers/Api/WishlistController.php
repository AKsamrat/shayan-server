<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $wishlist = Wishlist::with('product.images')
            ->where('user_id', $request->user()->id)
            ->get();

        return $this->success($wishlist);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return $this->error('Product already in wishlist', 422);
        }

        Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        return $this->success(null, 'Added to wishlist', 201);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->success(null, 'Removed from wishlist');
    }

    public function check(Request $request, int $productId): JsonResponse
    {
        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        return $this->success(['is_in_wishlist' => $exists]);
    }
}
