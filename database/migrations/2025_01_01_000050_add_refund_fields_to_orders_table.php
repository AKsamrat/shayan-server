<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add return_requested to the status enum and add refund-related columns
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned','refunded','return_requested') NOT NULL DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            $table->text('return_reason')->nullable()->after('notes');
            $table->text('return_notes')->nullable()->after('return_reason');
            $table->timestamp('return_requested_at')->nullable()->after('return_notes');
            $table->timestamp('refunded_at')->nullable()->after('return_requested_at');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refunded_at');
            $table->string('refund_reason')->nullable()->after('refund_amount');
            $table->string('refund_method')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'return_reason',
                'return_notes',
                'return_requested_at',
                'refunded_at',
                'refund_amount',
                'refund_reason',
                'refund_method',
            ]);
        });

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','processing','shipped','delivered','cancelled','returned','refunded') NOT NULL DEFAULT 'pending'");
    }
};
