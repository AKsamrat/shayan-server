<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('minimum_quantity')->default(0);
            $table->string('location')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->enum('status', ['in_stock', 'low_stock', 'out_of_stock', 'discontinued'])->default('in_stock');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_inventories');
    }
};
