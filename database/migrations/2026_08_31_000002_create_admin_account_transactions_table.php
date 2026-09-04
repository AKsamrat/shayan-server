<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('admin_accounts')->onDelete('cascade');
            $table->enum('transaction_type', ['deposit', 'withdrawal', 'order_payment', 'supplier_payment', 'transfer_in', 'transfer_out', 'adjustment', 'refund'])->default('deposit');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->enum('reference_type', ['manual', 'order', 'supplier', 'supplier_payment'])->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->foreignId('to_account_id')->nullable()->constrained('admin_accounts')->onDelete('set null');
            $table->string('payment_method')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_account_transactions');
    }
};
