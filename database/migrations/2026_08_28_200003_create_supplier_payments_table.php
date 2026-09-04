<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_routing_number')->nullable();
            $table->string('account_type')->nullable();
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->enum('balance_type', ['credit', 'debit'])->default('credit');
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_account_id')->nullable()->constrained('supplier_accounts')->nullOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('supplier_purchases')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_number')->unique();
            $table->enum('type', ['purchase', 'payment', 'refund', 'adjustment', 'other']);
            $table->decimal('amount', 12, 2);
            $table->enum('amount_type', ['credit', 'debit']);
            $table->decimal('balance_after', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
        Schema::dropIfExists('supplier_accounts');
    }
};
