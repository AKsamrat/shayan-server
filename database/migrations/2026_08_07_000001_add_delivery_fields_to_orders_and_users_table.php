<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add delivery_boy to users role enum
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'admin', 'vendor', 'super_admin', 'delivery_boy'])->default('customer')->change();
        });

        // Add delivery tracking fields to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_boy_id')->nullable()->constrained('users')->nullOnDelete()->after('shipping_method');
            $table->text('delivery_notes')->nullable()->after('delivery_boy_id');
            $table->timestamp('assigned_at')->nullable()->after('delivery_notes');
            $table->timestamp('picked_up_at')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_boy_id']);
            $table->dropColumn(['delivery_boy_id', 'delivery_notes', 'assigned_at', 'picked_up_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'admin', 'vendor', 'super_admin'])->default('customer')->change();
        });
    }
};
