<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $brands = Brand::withCount('products')
            ->where('is_active', true)
            ->get();

        return $this->success($brands);
    }

    public function show(string $slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)->firstOrFail();
        return $this->success($brand);
    }

    public function products(string $slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)->firstOrFail();

        $query = Product::with(['category', 'brand', 'images'])
            ->where('brand_id', $brand->id)
            ->where('is_active', true);

        $result = $this->paginated($query);
        return $this->success($result);
    }
}
