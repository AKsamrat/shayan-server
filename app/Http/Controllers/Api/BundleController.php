<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    use ApiResponse;

    // Get all bundles
    public function index(): JsonResponse
    {
        $bundles = Bundle::with('products')
            ->ordered()
            ->paginate(15);

        return $this->success($bundles);
    }

    // Get active bundles for frontend
    public function getActive(): JsonResponse
    {
        $bundles = Bundle::active()
            ->with('products')
            ->ordered()
            ->limit(3)
            ->get();

        return $this->success($bundles);
    }

    // Get single bundle
    public function show(int $id): JsonResponse
    {
        $bundle = Bundle::with('products')->findOrFail($id);
        return $this->success($bundle);
    }

    // Create bundle
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'badge' => 'required|string|max:100',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'product_ids' => 'required|array|min:2',
            'product_ids.*' => 'exists:products,id',
        ]);

        $bundle = Bundle::create($validated);

        // Attach products with sort order
        $products = $validated['product_ids'];
        $attachData = [];
        foreach ($products as $index => $productId) {
            $attachData[$productId] = ['sort_order' => $index];
        }
        $bundle->products()->attach($attachData);

        return $this->success($bundle->load('products'), 'Bundle created', 201);
    }

    // Update bundle
    public function update(Request $request, int $id): JsonResponse
    {
        $bundle = Bundle::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'badge' => 'sometimes|string|max:100',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'product_ids' => 'sometimes|array|min:2',
            'product_ids.*' => 'exists:products,id',
        ]);

        $bundle->update($validated);

        // Update products if provided
        if (isset($validated['product_ids'])) {
            $products = $validated['product_ids'];
            $attachData = [];
            foreach ($products as $index => $productId) {
                $attachData[$productId] = ['sort_order' => $index];
            }
            $bundle->products()->sync($attachData);
        }

        return $this->success($bundle->fresh()->load('products'), 'Bundle updated');
    }

    // Delete bundle
    public function destroy(int $id): JsonResponse
    {
        $bundle = Bundle::findOrFail($id);
        $bundle->delete();
        return $this->success(null, 'Bundle deleted');
    }

    // Reorder bundles
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bundles' => 'required|array',
            'bundles.*.id' => 'required|exists:bundles,id',
            'bundles.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['bundles'] as $item) {
            Bundle::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return $this->success(null, 'Bundle order updated');
    }
}
