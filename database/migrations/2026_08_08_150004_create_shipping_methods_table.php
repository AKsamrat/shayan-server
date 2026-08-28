<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('rate_type', ['flat', 'weight_based', 'price_based', 'free'])->default('flat');
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('per_kg_rate', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_order_amount', 12, 2)->nullable();
            $table->decimal('min_weight', 8, 2)->nullable();
            $table->decimal('max_weight', 8, 2)->nullable();
            $table->string('estimated_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('zone_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
