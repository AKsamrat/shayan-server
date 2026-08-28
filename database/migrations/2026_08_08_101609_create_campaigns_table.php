<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['email', 'sms', 'social', 'promotion', 'banner'])->default('promotion');
            $table->enum('status', ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->string('image')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->decimal('spent', 12, 2)->default(0);
            $table->integer('target_audience')->default(0);
            $table->integer('reached_audience')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
