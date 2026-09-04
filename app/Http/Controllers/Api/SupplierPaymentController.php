<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\SupplierTransaction;
use App\Models\SupplierPurchase;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierPaymentController extends Controller
{
    use ApiResponse;

    /**
     * Get all supplier accounts.
     */
    public function accounts(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $accounts = SupplierAccount::where('supplier_id', $supplierId)
            ->with(['transactions' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->get();

        return $this->success($accounts);
    }

    /**
     * Create a new supplier account.
     */
    public function createAccount(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|unique:supplier_accounts',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_routing_number' => 'nullable|string|max:100',
            'account_type' => 'nullable|string|max:50',
            'current_balance' => 'nullable|numeric',
            'balance_type' => 'required|in:credit,debit',
            'notes' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['supplier_id'] = $supplierId;

        // If this is set as default, unset default from others
        if ($data['is_default'] ?? false) {
            SupplierAccount::where('supplier_id', $supplierId)
                ->where('id', '!=', $request->id ?? 0)
                ->update(['is_default' => false]);
        }

        $account = SupplierAccount::create($data);

        return $this->success($account, 'Account created successfully', 201);
    }

    /**
     * Get a single supplier account with transactions.
     */
    public function showAccount(Request $request, int $accountId): JsonResponse
    {
        $account = SupplierAccount::with(['supplier', 'transactions.createdBy'])
            ->findOrFail($accountId);

        return $this->success($account);
    }

    /**
     * Update a supplier account.
     */
    public function updateAccount(Request $request, int $accountId): JsonResponse
    {
        $account = SupplierAccount::findOrFail($accountId);

        $validator = Validator::make($request->all(), [
            'account_number' => 'sometimes|string|unique:supplier_accounts,account_number,'.$accountId,
            'account_name' => 'sometimes|string|max:255',
            'bank_name' => 'sometimes|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'bank_routing_number' => 'nullable|string|max:100',
            'account_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        // If this is set as default, unset default from others
        if (isset($data['is_default']) && $data['is_default']) {
            SupplierAccount::where('supplier_id', $account->supplier_id)
                ->where('id', '!=', $accountId)
                ->update(['is_default' => false]);
        }

        $account->update($data);

        return $this->success($account->fresh(), 'Account updated successfully');
    }

    /**
     * Delete a supplier account.
     */
    public function deleteAccount(int $accountId): JsonResponse
    {
        $account = SupplierAccount::findOrFail($accountId);

        // Cannot delete default account
        if ($account->is_default) {
            return $this->error('Cannot delete the default account. Set another account as default first.', 422);
        }

        // Cannot delete if has transactions
        if ($account->transactions()->count() > 0) {
            return $this->error('Cannot delete account with existing transactions.', 422);
        }

        $account->delete();

        return $this->success(null, 'Account deleted successfully');
    }

    /**
     * Get all supplier transactions (across all suppliers).
     */
    public function allTransactions(Request $request): JsonResponse
    {
        $query = SupplierTransaction::query()
            ->with(['supplier:id,name'])
            ->where('type', 'payment');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by amount type
        if ($request->filled('amount_type')) {
            $query->where('amount_type', $request->amount_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by account
        if ($request->filled('account_id')) {
            $query->where('supplier_account_id', $request->account_id);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $orderBy = $request->get('order_by', 'transaction_date');
        $orderDir = $request->get('order_dir', 'desc');
        
        if (in_array($orderBy, ['transaction_date', 'amount', 'transaction_number', 'supplier_id'])) {
            $query->orderBy($orderBy, $orderDir);
        }

        $transactions = $query->paginate($request->get('per_page', 20));

        return $this->success($transactions);
    }

    /**
     * Get all transactions for a supplier.
     */
    public function transactions(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $query = SupplierTransaction::query()
            ->with(['createdBy', 'account', 'purchase'])
            ->where('supplier_id', $supplierId);

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by amount type
        if ($request->filled('amount_type')) {
            $query->where('amount_type', $request->amount_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filter by account
        if ($request->filled('account_id')) {
            $query->where('supplier_account_id', $request->account_id);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $orderBy = $request->get('order_by', 'transaction_date');
        $orderDir = $request->get('order_dir', 'desc');
        
        if (in_array($orderBy, ['transaction_date', 'amount', 'transaction_number'])) {
            $query->orderBy($orderBy, $orderDir);
        }

        $transactions = $query->paginate($request->get('per_page', 20));

        // Calculate running balance
        $transactions->getCollection()->transform(function ($transaction, $index) use ($transactions) {
            if ($index === 0) {
                $previousBalance = $transaction->supplier->opening_balance ?? 0;
            } else {
                $previousBalance = $transactions->getCollection()->get($index - 1)->running_balance ?? 0;
            }
            $transaction->running_balance = $previousBalance + 
                ($transaction->amount_type === 'credit' ? $transaction->amount : -$transaction->amount);
            return $transaction;
        });

        return $this->success($transactions);
    }

    /**
     * Make a payment to a supplier.
     */
    public function makePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_account_id' => 'nullable|exists:supplier_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,bank_transfer,check,mobile_banking,credit_card,other',
            'reference_number' => 'nullable|string',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['created_by'] = $request->user()->id;

        DB::beginTransaction();
        try {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            
            // Get current balance before payment
            $currentBalance = $supplier->current_balance;
            $balanceAfter = $currentBalance - $data['amount'];

            // Create transaction
            $transaction = SupplierTransaction::create([
                'supplier_id' => $data['supplier_id'],
                'supplier_account_id' => $data['supplier_account_id'],
                'created_by' => $data['created_by'],
                'type' => 'payment',
                'amount' => $data['amount'],
                'amount_type' => 'credit', // Payment is credit to supplier (we owe them less)
                'balance_after' => $balanceAfter,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'],
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? 'Payment to supplier',
                'notes' => $data['notes'],
                'status' => 'completed',
            ]);

            // Update purchase payment status if this payment is for specific purchases
            if ($request->has('purchase_ids') && !empty($request->purchase_ids)) {
                $purchaseIds = $request->purchase_ids;
                $totalAmount = $data['amount'];
                $remainingAmount = $totalAmount;

                foreach ($purchaseIds as $purchaseId) {
                    if ($remainingAmount <= 0) break;

                    $purchase = SupplierPurchase::find($purchaseId);
                    if ($purchase && $purchase->amount_due > 0) {
                        $payAmount = min($purchase->amount_due, $remainingAmount);
                        $purchase->increment('amount_paid', $payAmount);
                        $purchase->decrement('amount_due', $payAmount);
                        $remainingAmount -= $payAmount;

                        // Update payment status
                        if ($purchase->amount_due <= 0) {
                            $purchase->payment_status = 'paid';
                        } elseif ($purchase->amount_paid > 0) {
                            $purchase->payment_status = 'partial';
                        }
                        $purchase->save();
                    }
                }
            }

            // Handle file attachment upload
            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('supplier_attachments', 'public');
                $transaction->attachment = $path;
                $transaction->save();
            }

            DB::commit();

            return $this->success($transaction->load(['supplier', 'account', 'createdBy']), 'Payment recorded successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get transactions for a specific account.
     */
    public function accountTransactions(Request $request, int $accountId): JsonResponse
    {
        $account = SupplierAccount::findOrFail($accountId);

        $query = SupplierTransaction::query()
            ->with(['createdBy', 'purchase'])
            ->where('supplier_account_id', $accountId);

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by amount type
        if ($request->filled('amount_type')) {
            $query->where('amount_type', $request->amount_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $orderBy = $request->get('order_by', 'transaction_date');
        $orderDir = $request->get('order_dir', 'desc');
        
        if (in_array($orderBy, ['transaction_date', 'amount', 'transaction_number'])) {
            $query->orderBy($orderBy, $orderDir);
        }

        $transactions = $query->paginate($request->get('per_page', 20));

        return $this->success($transactions);
    }

    /**
     * Get a single transaction by ID.
     */
    public function showTransaction(int $transactionId): JsonResponse
    {
        $transaction = SupplierTransaction::with([
            'account:id,account_name,bank_name',
            'purchase:id,purchase_number',
            'supplier:id,name'
        ])
            ->findOrFail($transactionId);

        // Load created_by user separately to avoid column name conflict
        $createdBy = null;
        if ($transaction->created_by) {
            $createdBy = User::find($transaction->created_by, ['id', 'name']);
        }
        $transaction->createdBy = $createdBy;
        unset($transaction->created_by);

        return $this->success($transaction);
    }

    /**
     * Get payment history for a supplier.
     */
    public function paymentHistory(Request $request, int $supplierId): JsonResponse
    {
        $query = SupplierTransaction::query()
            ->with(['createdBy', 'account'])
            ->where('supplier_id', $supplierId)
            ->where('type', 'payment');

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 15));

        return $this->success($payments);
    }

    /**
     * Get outstanding balance and payments for a supplier.
     */
    public function balanceSummary(Request $request, int $supplierId): JsonResponse
    {
        $supplier = Supplier::findOrFail($supplierId);

        $totalPurchases = $supplier->purchases()->sum('total');
        $totalPaid = $supplier->transactions()
            ->where('type', 'payment')
            ->where('amount_type', 'credit')
            ->sum('amount');

        $outstandingBalance = $supplier->current_balance;

        // Get recent payments
        $recentPayments = $supplier->transactions()
            ->where('type', 'payment')
            ->latest()
            ->limit(5)
            ->get();

        // Get upcoming purchases (not fully paid)
        $unpaidPurchases = $supplier->purchases()
            ->where('payment_status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->with('items.product')
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'opening_balance' => $supplier->opening_balance,
            'total_purchases' => $totalPurchases,
            'total_paid' => $totalPaid,
            'outstanding_balance' => $outstandingBalance,
            'balance_type' => $outstandingBalance >= 0 ? 'credit' : 'debit',
            'recent_payments' => $recentPayments,
            'unpaid_purchases' => $unpaidPurchases,
        ]);
    }
}
