<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('order_number');
            $table->string('partner');
            $table->string('tracking_id')->nullable();
            $table->enum('status', ['pending', 'picked', 'in_transit', 'delivered', 'returned', 'failed'])->default('pending');
            $table->text('pickup_address');
            $table->text('delivery_address');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->decimal('cod_amount', 12, 2)->nullable();
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->string('estimated_delivery')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('order_id');
            $table->index('partner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_bookings');
    }
};
