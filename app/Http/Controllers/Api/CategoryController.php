<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $categories = Category::with(['children' => function ($q) {
            $q->withCount('products');
        }, 'products' => function ($q) {
            $q->where('is_active', true)->limit(7);
        }])
            ->withCount('products')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::with(['children', 'products' => function ($q) {
            $q->where('is_active', true);
        }])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success($category);
    }

    public function products(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $query = Product::with(['category', 'brand', 'images'])
            ->where('category_id', $category->id)
            ->where('is_active', true);

        if ($sortBy = $request->sort_by) {
            $sortDir = $request->get('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);
        }

        $result = $this->paginated($query);
        return $this->success($result);
    }
}
