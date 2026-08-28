<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a coupon be scoped to a single category OR a single product.
 * Both nullable: when both are null the coupon applies store-wide (as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('maximum_discount')
                    ->constrained('categories')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('coupons', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
            if (Schema::hasColumn('coupons', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }
};
