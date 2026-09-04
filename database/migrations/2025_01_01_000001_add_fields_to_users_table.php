<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->enum('role', ['customer', 'admin', 'vendor', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager', 'delivery_boy'])->default('customer')->after('avatar');
            $table->boolean('is_verified')->default(false)->after('role');
            $table->boolean('is_active')->default(true)->after('is_verified');
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'role', 'is_verified', 'is_active', 'two_factor_enabled']);
        });
    }
};
