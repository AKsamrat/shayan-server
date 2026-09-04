<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierPurchaseItem;
use App\Models\SupplierInventory;
use App\Models\SupplierTransaction;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierPurchaseController extends Controller
{
    use ApiResponse;

    /**
     * Get all purchases with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupplierPurchase::query()
            ->with(['supplier', 'createdBy', 'items.product'])
            ->withCount(['items', 'payments']);

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'purchase_date');
        $orderDir = $request->get('order_dir', 'desc');
        
        if (in_array($orderBy, ['purchase_date', 'purchase_number', 'total', 'status'])) {
            $query->orderBy($orderBy, $orderDir);
        }

        $purchases = $query->paginate($request->get('per_page', 15));

        return $this->success($purchases);
    }

    /**
     * Create a new purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:purchase_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['created_by'] = $request->user()->id;
        $data['purchase_number'] = SupplierPurchase::generatePurchaseNumber();
        $data['status'] = 'pending';
        $data['payment_status'] = 'unpaid';

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $itemTotal = $item['unit_price'] * $item['quantity'];
                $itemDiscount = ($item['discount'] ?? 0) * $item['quantity'];
                $itemTax = ($item['tax'] ?? 0) * $item['quantity'];
                $subtotal += $itemTotal - $itemDiscount + $itemTax;
            }

            $data['subtotal'] = $subtotal;
            $data['total'] = $subtotal + ($data['tax'] ?? 0) + ($data['shipping_cost'] ?? 0) - ($data['discount'] ?? 0);
            $data['amount_paid'] = 0;
            $data['amount_due'] = $data['total'];

            // Create purchase
            $purchase = SupplierPurchase::create($data);

            // Create purchase items
            foreach ($data['items'] as $item) {
                $itemData = [
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => ($item['unit_price'] * $item['quantity']),
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                    'received_quantity' => $item['quantity'], // Mark all as received immediately
                ];

                $purchaseItem = SupplierPurchaseItem::create($itemData);

                // Update or create supplier inventory
                $inventory = SupplierInventory::where('supplier_id', $data['supplier_id'])
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($inventory) {
                    // Update cost price and increase quantity
                    $inventory->update([
                        'cost_price' => $item['unit_price'],
                    ]);
                    $inventory->increment('quantity', $item['quantity']);
                    
                    // Update status based on quantity
                    if ($inventory->quantity <= 0) {
                        $inventory->status = 'out_of_stock';
                    } elseif ($inventory->quantity <= ($inventory->minimum_quantity ?? 0)) {
                        $inventory->status = 'low_stock';
                    } else {
                        $inventory->status = 'in_stock';
                    }
                    $inventory->save();
                } else {
                    SupplierInventory::create([
                        'supplier_id' => $data['supplier_id'],
                        'product_id' => $item['product_id'],
                        'cost_price' => $item['unit_price'],
                        'quantity' => $item['quantity'],
                        'status' => 'in_stock',
                    ]);
                }
            }

            // Update purchase status to received since all items are received
            $purchase->update([
                'status' => 'received',
                'delivery_date' => $data['purchase_date'],
                'is_fully_received' => true,
            ]);

            // Update supplier balance
            $purchase->supplier->increment('current_balance', $purchase->total);
            
            // Get the updated balance
            $purchase->supplier->refresh();
            $newBalance = $purchase->supplier->current_balance;

            // Create transaction for this purchase
            SupplierTransaction::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_id' => $purchase->id,
                'created_by' => $request->user()->id,
                'type' => 'purchase',
                'amount' => $purchase->total,
                'amount_type' => 'debit',
                'balance_after' => $newBalance,
                'payment_method' => $data['payment_method'] ?? null,
                'description' => 'Purchase Order #' . $purchase->purchase_number,
                'transaction_date' => $data['purchase_date'],
                'status' => 'completed',
            ]);

            DB::commit();

            return $this->success($purchase->load(['items.product', 'supplier']), 'Purchase order created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single purchase with all details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $purchase = SupplierPurchase::with([
            'supplier',
            'createdBy',
            'items.product',
            'transactions',
            'payments',
        ])->findOrFail($id);

        return $this->success($purchase);
    }

    /**
     * Update purchase status (receive items).
     */
    public function receiveItems(Request $request, int $id): JsonResponse
    {
        $purchase = SupplierPurchase::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'items.*.item_id' => 'required|exists:supplier_purchase_items,id',
            'items.*.received_quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                $purchaseItem = SupplierPurchaseItem::findOrFail($item['item_id']);
                
                // Ensure we don't receive more than ordered
                $maxQuantity = $purchaseItem->quantity - $purchaseItem->received_quantity;
                $actualReceived = min($item['received_quantity'], $maxQuantity);

                $purchaseItem->increment('received_quantity', $actualReceived);

                // Update inventory
                $inventory = SupplierInventory::where('supplier_id', $purchase->supplier_id)
                    ->where('product_id', $purchaseItem->product_id)
                    ->first();

                if ($inventory) {
                    $inventory->increment('quantity', $actualReceived);
                    
                    // Update status based on quantity
                    if ($inventory->quantity <= 0) {
                        $inventory->status = 'out_of_stock';
                    } elseif ($inventory->quantity <= ($inventory->minimum_quantity ?? 0)) {
                        $inventory->status = 'low_stock';
                    } else {
                        $inventory->status = 'in_stock';
                    }
                    $inventory->save();
                }
            }

            // Update purchase status
            if ($purchase->is_fully_received) {
                $purchase->update(['status' => 'received', 'delivery_date' => now()]);
            } else {
                $purchase->update(['status' => 'partial']);
            }

            DB::commit();

            return $this->success($purchase->fresh()->load(['items.product']), 'Items received successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to receive items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update purchase (edit before receiving).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $purchase = SupplierPurchase::findOrFail($id);

        // Cannot edit after receiving items
        if ($purchase->received_items > 0) {
            return $this->error('Cannot edit purchase after items have been received', 422);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'purchase_date' => 'sometimes|date',
            'expected_delivery_date' => 'sometimes|nullable|date|after_or_equal:purchase_date',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'sometimes|exists:supplier_purchase_items,id',
            'items.*.product_id' => 'sometimes|required_with:items|exists:products,id',
            'items.*.quantity' => 'sometimes|required_with:items|integer|min:1',
            'items.*.unit_price' => 'sometimes|required_with:items|numeric|min:0',
            'tax' => 'sometimes|nullable|numeric|min:0',
            'discount' => 'sometimes|nullable|numeric|min:0',
            'shipping_cost' => 'sometimes|nullable|numeric|min:0',
            'payment_method' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'terms_conditions' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            // Update purchase
            $purchase->update($data);

            // Handle items update
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    if (isset($item['id'])) {
                        // Update existing item
                        $purchaseItem = SupplierPurchaseItem::findOrFail($item['id']);
                        $purchaseItem->update([
                            'product_id' => $item['product_id'] ?? $purchaseItem->product_id,
                            'quantity' => $item['quantity'] ?? $purchaseItem->quantity,
                            'unit_price' => $item['unit_price'] ?? $purchaseItem->unit_price,
                            'total_price' => ($item['unit_price'] ?? $purchaseItem->unit_price) * ($item['quantity'] ?? $purchaseItem->quantity),
                        ]);
                    } else {
                        // Add new item
                        SupplierPurchaseItem::create([
                            'purchase_id' => $purchase->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['unit_price'] * $item['quantity'],
                            'received_quantity' => 0,
                        ]);
                    }
                }
            }

            // Recalculate totals
            $subtotal = $purchase->items()->sum(\DB::raw('unit_price * quantity'));
            $total = $subtotal + ($purchase->tax ?? 0) + ($purchase->shipping_cost ?? 0) - ($purchase->discount ?? 0);
            $purchase->update([
                'subtotal' => $subtotal,
                'total' => $total,
                'amount_due' => $total - $purchase->amount_paid,
            ]);

            DB::commit();

            return $this->success($purchase->fresh()->load(['items.product', 'supplier']), 'Purchase updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a purchase.
     */
    public function destroy(int $id): JsonResponse
    {
        $purchase = SupplierPurchase::findOrFail($id);

        // Cannot delete after receiving items or making payments
        if ($purchase->received_items > 0 || $purchase->amount_paid > 0) {
            return $this->error('Cannot delete purchase with received items or payments', 422);
        }

        $purchase->delete();

        return $this->success(null, 'Purchase deleted successfully');
    }

    /**
     * Get recent purchases for dashboard.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $purchases = SupplierPurchase::with(['supplier', 'createdBy'])
            ->latest()
            ->limit($limit)
            ->get();

        return $this->success($purchases);
    }
}
