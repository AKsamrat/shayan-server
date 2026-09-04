<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierInventory;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    use ApiResponse;

    /**
     * Get all suppliers with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->with(['inventory.product'])
            ->withCount(['purchases', 'inventory', 'transactions']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'name');
        $orderDir = $request->get('order_dir', 'asc');
        
        if (in_array($orderBy, ['name', 'email', 'phone', 'created_at', 'current_balance'])) {
            $query->orderBy($orderBy, $orderDir);
        }

        $suppliers = $query->paginate($request->get('per_page', 15));

        return $this->success($suppliers);
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:suppliers',
            'phone' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:100',
            'business_license' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive,suspended',
            'notes' => 'nullable|string',
            'opening_balance' => 'nullable|numeric|min:0',
            'balance_type' => 'required|in:credit,debit',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        DB::beginTransaction();
        try {
            $supplier = Supplier::create($data);

            // Create default account for this supplier
            $supplier->accounts()->create([
                'account_name' => $supplier->name . ' Account',
                'current_balance' => $supplier->opening_balance,
                'balance_type' => $supplier->balance_type,
                'is_default' => true,
            ]);

            // If inventory items were provided, create them
            if ($request->has('inventory')) {
                foreach ($request->inventory as $item) {
                    $this->createInventoryItem($supplier->id, $item);
                }
            }

            DB::commit();

            return $this->success($supplier->load(['accounts', 'inventory.product']), 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single supplier with all related data.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::with([
            'inventory.product',
            'accounts',
            'purchases' => function ($query) {
                $query->latest()->limit(10);
            },
            'transactions' => function ($query) {
                $query->latest()->limit(20);
            },
        ])->findOrFail($id);

        // Calculate total purchases and payments
        $totalPurchases = $supplier->purchases()->sum('total');
        $totalPaid = $supplier->transactions()
            ->where('type', 'payment')
            ->where('amount_type', 'credit')
            ->sum('amount');

        $supplierData = $supplier->toArray();
        $supplierData['total_purchases'] = $totalPurchases;
        $supplierData['total_paid'] = $totalPaid;
        $supplierData['outstanding_balance'] = $supplier->current_balance;

        return $this->success($supplierData);
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|unique:suppliers,email,'.$id,
            'phone' => 'sometimes|nullable|string|max:50',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'tax_id' => 'sometimes|nullable|string|max:100',
            'business_license' => 'sometimes|nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,suspended',
            'notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $supplier->update($data);

        return $this->success($supplier->fresh(), 'Supplier updated successfully');
    }

    /**
     * Delete a supplier.
     */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        // Check if supplier has any purchases or transactions
        $purchaseCount = $supplier->purchases()->count();
        $transactionCount = $supplier->transactions()->count();

        if ($purchaseCount > 0 || $transactionCount > 0) {
            return $this->error('Cannot delete supplier with existing purchases or transactions. Archive instead.', 422);
        }

        $supplier->delete();

        return $this->success(null, 'Supplier deleted successfully');
    }

    /**
     * Get supplier inventory with filtering.
     */
    public function inventory(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $query = SupplierInventory::query()
            ->with(['product'])
            ->where('supplier_id', $supplierId);

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by low stock
        if ($request->filled('low_stock')) {
            $query->whereRaw('quantity <= minimum_quantity');
        }

        $inventory = $query->paginate($request->get('per_page', 20));

        return $this->success($inventory);
    }

    /**
     * Get a single inventory item.
     */
    public function getInventoryItem(int $supplierId, int $inventoryId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $inventory = SupplierInventory::query()
            ->with(['product'])
            ->where('supplier_id', $supplierId)
            ->where('id', $inventoryId)
            ->firstOrFail();

        return $this->success($inventory);
    }

    /**
     * Add or update inventory item for a supplier.
     */
    public function updateInventory(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'minimum_quantity' => 'nullable|integer|min:0',
            'location' => 'nullable|string',
            'sku' => 'nullable|string',
            'barcode' => 'nullable|string',
            'batch_number' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|in:in_stock,low_stock,out_of_stock,discontinued',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['supplier_id'] = $supplierId;

        // Check if inventory item already exists for this product
        $inventory = SupplierInventory::where('supplier_id', $supplierId)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($inventory) {
            $inventory->update($data);
            $message = 'Inventory updated successfully';
        } else {
            $inventory = SupplierInventory::create($data);
            $message = 'Inventory added successfully';
        }

        return $this->success($inventory->load('product'), $message);
    }

    /**
     * Get supplier statistics dashboard.
     */
    public function stats(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $totalPurchases = $supplier->purchases()->count();
        $totalPurchaseValue = $supplier->purchases()->sum('total');
        $totalPaid = $supplier->transactions()
            ->where('type', 'payment')
            ->where('amount_type', 'credit')
            ->sum('amount');
        
        $totalInventoryItems = $supplier->inventory()->count();
        $totalInventoryValue = $supplier->inventory()->sum(
            \DB::raw('cost_price * quantity')
        );
        $lowStockItems = $supplier->inventory()
            ->whereRaw('quantity <= minimum_quantity')
            ->count();
        $outOfStockItems = $supplier->inventory()
            ->where('quantity', 0)
            ->count();

        return $this->success([
            'total_purchases' => $totalPurchases,
            'total_purchase_value' => $totalPurchaseValue,
            'total_paid' => $totalPaid,
            'outstanding_balance' => $supplier->current_balance,
            'total_inventory_items' => $totalInventoryItems,
            'total_inventory_value' => $totalInventoryValue,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
        ]);
    }

    /**
     * Helper method to create inventory item.
     */
    private function createInventoryItem(int $supplierId, array $item): void
    {
        SupplierInventory::create([
            'supplier_id' => $supplierId,
            'product_id' => $item['product_id'] ?? null,
            'sku' => $item['sku'] ?? null,
            'barcode' => $item['barcode'] ?? null,
            'cost_price' => $item['cost_price'] ?? 0,
            'selling_price' => $item['selling_price'] ?? null,
            'quantity' => $item['quantity'] ?? 0,
            'minimum_quantity' => $item['minimum_quantity'] ?? 0,
            'location' => $item['location'] ?? null,
            'batch_number' => $item['batch_number'] ?? null,
            'expiry_date' => $item['expiry_date'] ?? null,
            'status' => $item['status'] ?? 'in_stock',
            'notes' => $item['notes'] ?? null,
        ]);
    }
}
