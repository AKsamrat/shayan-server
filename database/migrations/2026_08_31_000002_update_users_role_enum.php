<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support modifying ENUM columns directly with Schema::table()
        // We need to use raw SQL to modify the ENUM to include all role slugs
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'vendor', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager', 'delivery_boy') DEFAULT 'customer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'vendor', 'super_admin') DEFAULT 'customer'");
    }
};
