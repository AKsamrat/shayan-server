<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained('vendor_shops')->nullOnDelete();
            $table->boolean('is_approved')->default(true)->after('vendor_id');
            $table->text('rejection_reason')->nullable()->after('is_approved');
            $table->decimal('vendor_commission', 5, 2)->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'is_approved', 'rejection_reason', 'vendor_commission']);
        });
    }
};
