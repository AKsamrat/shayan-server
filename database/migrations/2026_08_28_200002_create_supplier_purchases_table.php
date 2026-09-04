<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->date('purchase_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->enum('status', ['draft', 'pending', 'received', 'partial', 'cancelled'])->default('draft');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue', 'cancelled'])->default('unpaid');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('supplier_purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_inventory_id')->nullable()->constrained('supplier_inventories')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->integer('received_quantity')->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_items');
        Schema::dropIfExists('supplier_purchases');
    }
};
