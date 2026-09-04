<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminAccount;
use App\Models\AdminAccountTransaction;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    use ApiResponse;

    // ==================== ACCOUNTS ====================

    /**
     * Get all admin accounts with pagination and filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $accountType = $request->input('account_type');
        $isActive = $request->input('is_active');

        $query = AdminAccount::query();

        if ($search) {
            $query->where('name', 'like', '%' . $search . '%')
                  ->orWhere('account_number', 'like', '%' . $search . '%')
                  ->orWhere('bank_name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
        }

        if ($accountType) {
            $query->where('account_type', $accountType);
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $query->orderBy('is_default', 'desc')
              ->orderBy('name', 'asc');

        $accounts = $query->paginate($perPage);

        return $this->success([
            'data' => $accounts->items(),
            'current_page' => $accounts->currentPage(),
            'last_page' => $accounts->lastPage(),
            'per_page' => $accounts->perPage(),
            'total' => $accounts->total(),
        ]);
    }

    /**
     * Get a single admin account by ID.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $account = AdminAccount::with(['transactions' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail((int)$id);

        return $this->success($account);
    }

    /**
     * Create a new admin account.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'account_type' => 'required|in:cash,bank,mobile_banking,credit_card,digital_wallet,other',
            'account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:100',
            'initial_balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        // If initial_balance is not provided, set it to 0
        $data['initial_balance'] = $data['initial_balance'] ?? 0;
        $data['current_balance'] = $data['initial_balance'] ?? 0;
        $data['currency'] = $data['currency'] ?? 'USD';

        // If this is set as default, unset default from others
        if (($data['is_default'] ?? false) === true) {
            AdminAccount::where('is_default', true)->update(['is_default' => false]);
        }

        try {
            DB::beginTransaction();

            $account = AdminAccount::create($data);

            // Create initial transaction for the initial balance
            if ($account->initial_balance > 0) {
                AdminAccountTransaction::create([
                    'account_id' => $account->id,
                    'transaction_type' => 'deposit',
                    'amount' => $account->initial_balance,
                    'balance_after' => $account->current_balance,
                    'description' => 'Initial balance deposit',
                    'reference_id' => null,
                    'reference_type' => 'manual',
                    'transaction_date' => now(),
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return $this->success($account, 'Account created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Update an admin account.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $account = AdminAccount::findOrFail((int)$id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'account_type' => 'sometimes|in:cash,bank,mobile_banking,credit_card,digital_wallet,other',
            'account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        // If this is set as default, unset default from others
        if (isset($data['is_default']) && $data['is_default'] === true) {
            AdminAccount::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $account->update($data);

        return $this->success($account, 'Account updated successfully');
    }

    /**
     * Delete an admin account.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $account = AdminAccount::findOrFail((int)$id);

        // Prevent deletion of default account
        if ($account->is_default) {
            return $this->error('Cannot delete the default account', 400);
        }

        $account->delete();

        return $this->success(null, 'Account deleted successfully');
    }

    /**
     * Set an account as the default.
     */
    public function setDefault(Request $request, $id): JsonResponse
    {
        $account = AdminAccount::findOrFail((int)$id);

        // Unset default from all other accounts
        AdminAccount::where('is_default', true)->update(['is_default' => false]);

        $account->update(['is_default' => true]);

        return $this->success($account, 'Default account updated successfully');
    }

    /**
     * Get account statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $totalAccounts = AdminAccount::count();
        $totalBalance = AdminAccount::sum('current_balance');

        $accountsByType = AdminAccount::selectRaw('account_type, COUNT(*) as count, SUM(current_balance) as balance')
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type')
            ->map(function ($item) {
                return [
                    'count' => $item->count,
                    'balance' => $item->balance,
                ];
            });

        $activeAccounts = AdminAccount::where('is_active', true)->count();
        $inactiveAccounts = AdminAccount::where('is_active', false)->count();

        $stats = [
            'total_accounts' => $totalAccounts,
            'total_balance' => $totalBalance,
            'active_accounts' => $activeAccounts,
            'inactive_accounts' => $inactiveAccounts,
            'accounts_by_type' => $accountsByType,
        ];

        return $this->success($stats);
    }

    // ==================== TRANSACTIONS ====================

    /**
     * Get all account transactions with filtering.
     */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $accountId = $request->input('account_id');
        $transactionType = $request->input('transaction_type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');

        $query = AdminAccountTransaction::with(['account', 'toAccount', 'supplier', 'creator']);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($transactionType) {
            $query->where('transaction_type', $transactionType);
        }

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        if ($search) {
            $query->where('description', 'like', '%' . $search . '%')
                  ->orWhere('reference_id', 'like', '%' . $search . '%');
        }

        $query->latest();

        $transactions = $query->paginate($perPage);

        return $this->success([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
        ]);
    }

    /**
     * Get transactions for a specific account (statement).
     */
    public function statement(Request $request, $accountId): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $account = AdminAccount::findOrFail((int)$accountId);

        $query = AdminAccountTransaction::with(['toAccount', 'supplier', 'creator'])
            ->where('account_id', $accountId);

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $query->latest();

        $transactions = $query->get();

        // Return in PaginatedResponse format to match frontend expectations
        return $this->success([
            'data' => $transactions,
        ]);
    }

    /**
     * Get a single transaction by ID.
     */
    public function showTransaction(Request $request, $id): JsonResponse
    {
        $transaction = AdminAccountTransaction::with(['account', 'toAccount', 'supplier', 'creator'])
            ->findOrFail((int)$id);

        return $this->success($transaction);
    }

    /**
     * Create a new account transaction.
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:admin_accounts,id',
            'transaction_type' => 'required|in:deposit,withdrawal,order_payment,supplier_payment,transfer_in,transfer_out,adjustment,refund',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'reference_id' => 'nullable|string',
            'reference_type' => 'nullable|in:manual,order,supplier,supplier_payment',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $account = AdminAccount::findOrFail($data['account_id']);

        try {
            DB::beginTransaction();

            // Determine the amount sign based on transaction type
            $amount = $data['amount'];
            $transactionAmount = $amount;

            // For withdrawals, supplier payments, and transfer out, the amount is negative
            if (in_array($data['transaction_type'], ['withdrawal', 'supplier_payment', 'transfer_out'])) {
                $transactionAmount = -$amount;
            }

            // Calculate new balance
            $newBalance = $account->current_balance + $transactionAmount;

            // Create the transaction
            $transaction = AdminAccountTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => $data['transaction_type'],
                'amount' => $transactionAmount,
                'balance_after' => $newBalance,
                'description' => $data['description'] ?? '',
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? 'manual',
                'supplier_id' => $data['supplier_id'] ?? null,
                'to_account_id' => $data['to_account_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);

            // Update account balance
            $account->update(['current_balance' => $newBalance]);

            DB::commit();

            return $this->success($transaction, 'Transaction created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Update a transaction.
     */
    public function updateTransaction(Request $request, $id): JsonResponse
    {
        $transaction = AdminAccountTransaction::findOrFail((int)$id);

        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'reference_id' => 'nullable|string',
            'reference_type' => 'nullable|in:manual,order,supplier,supplier_payment',
            'payment_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $transaction->update($data);

        return $this->success($transaction, 'Transaction updated successfully');
    }

    /**
     * Delete a transaction.
     */
    public function destroyTransaction(Request $request, $id): JsonResponse
    {
        $transaction = AdminAccountTransaction::findOrFail((int)$id);
        $account = $transaction->account;

        try {
            DB::beginTransaction();

            // Revert the balance change
            $revertedBalance = $account->current_balance - $transaction->amount;
            $account->update(['current_balance' => $revertedBalance]);

            $transaction->delete();

            DB::commit();

            return $this->success(null, 'Transaction deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }

    // ==================== TRANSFERS ====================

    /**
     * Transfer funds between accounts.
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:admin_accounts,id',
            'to_account_id' => 'required|exists:admin_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        $fromAccount = AdminAccount::findOrFail($data['from_account_id']);
        $toAccount = AdminAccount::findOrFail($data['to_account_id']);

        // Check if from account has sufficient balance
        if ($fromAccount->current_balance < $data['amount']) {
            return $this->error('Insufficient balance in the source account', 400);
        }

        try {
            DB::beginTransaction();

            $amount = $data['amount'];

            // Create withdrawal transaction for source account
            $fromBalanceAfter = $fromAccount->current_balance - $amount;
            $fromTransaction = AdminAccountTransaction::create([
                'account_id' => $fromAccount->id,
                'transaction_type' => 'transfer_out',
                'amount' => -$amount,
                'balance_after' => $fromBalanceAfter,
                'description' => $data['description'] ?? 'Transfer to ' . $toAccount->name,
                'reference_id' => null,
                'reference_type' => 'manual',
                'to_account_id' => $toAccount->id,
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);

            // Create deposit transaction for destination account
            $toBalanceAfter = $toAccount->current_balance + $amount;
            $toTransaction = AdminAccountTransaction::create([
                'account_id' => $toAccount->id,
                'transaction_type' => 'transfer_in',
                'amount' => $amount,
                'balance_after' => $toBalanceAfter,
                'description' => $data['description'] ?? 'Transfer from ' . $fromAccount->name,
                'reference_id' => null,
                'reference_type' => 'manual',
                'to_account_id' => $fromAccount->id,
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);

            // Update account balances
            $fromAccount->update(['current_balance' => $fromBalanceAfter]);
            $toAccount->update(['current_balance' => $toBalanceAfter]);

            DB::commit();

            return $this->success([
                'from_transaction' => $fromTransaction,
                'to_transaction' => $toTransaction,
            ], 'Transfer completed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }

    // ==================== SUPPLIER PAYMENTS ====================

    /**
     * Pay a supplier from an admin account.
     */
    public function paySupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:admin_accounts,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
            'reference' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();

        $account = AdminAccount::findOrFail($data['account_id']);
        $supplier = Supplier::findOrFail($data['supplier_id']);

        // Check if account has sufficient balance
        if ($account->current_balance < $data['amount']) {
            return $this->error('Insufficient balance in the account', 400);
        }

        try {
            DB::beginTransaction();

            $amount = $data['amount'];
            $newBalance = $account->current_balance - $amount;

            // Create the transaction
            $transaction = AdminAccountTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'supplier_payment',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => $data['description'] ?? 'Payment to supplier: ' . $supplier->name,
                'reference_id' => $data['reference'] ?? null,
                'reference_type' => 'supplier_payment',
                'supplier_id' => $supplier->id,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);

            // Update account balance
            $account->update(['current_balance' => $newBalance]);

            DB::commit();

            return $this->success($transaction, 'Supplier payment completed successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Get supplier payments from accounts.
     */
    public function supplierPayments(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $accountId = $request->input('account_id');
        $supplierId = $request->input('supplier_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = AdminAccountTransaction::with(['account', 'supplier', 'creator'])
            ->where('transaction_type', 'supplier_payment');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }

        $query->latest();

        $payments = $query->paginate($perPage);

        return $this->success([
            'data' => $payments->items(),
            'current_page' => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
        ]);
    }

    // ==================== BALANCE ADJUSTMENT ====================

    /**
     * Adjust account balance.
     */
    public function adjustBalance(Request $request, $accountId): JsonResponse
    {
        $account = AdminAccount::findOrFail((int)$accountId);

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:add,deduct',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        $data = $validator->validated();
        $amount = $data['amount'];

        try {
            DB::beginTransaction();

            if ($data['type'] === 'add') {
                // Adding to balance
                $newBalance = $account->current_balance + $amount;
                $transactionAmount = $amount;
                $transactionType = 'deposit';
            } else {
                // Deducting from balance
                if ($account->current_balance < $amount) {
                    return $this->error('Insufficient balance for deduction', 400);
                }
                $newBalance = $account->current_balance - $amount;
                $transactionAmount = -$amount;
                $transactionType = 'withdrawal';
            }

            // Create adjustment transaction
            $transaction = AdminAccountTransaction::create([
                'account_id' => $account->id,
                'transaction_type' => 'adjustment',
                'amount' => $transactionAmount,
                'balance_after' => $newBalance,
                'description' => $data['description'] ?? 'Balance adjustment',
                'reference_id' => null,
                'reference_type' => 'manual',
                'transaction_date' => now(),
                'created_by' => auth()->id(),
            ]);

            // Update account balance
            $account->update(['current_balance' => $newBalance]);

            DB::commit();

            return $this->success($transaction, 'Balance adjusted successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 500);
        }
    }
}
