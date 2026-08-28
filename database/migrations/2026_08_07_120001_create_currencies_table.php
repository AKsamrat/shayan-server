<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217 code (USD, BDT, EUR, etc.)
            $table->string('name'); // US Dollar, Bangladeshi Taka, Euro
            $table->string('symbol'); // $, ৳, €
            $table->string('native_symbol')->nullable(); // Native symbol if different
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->decimal('exchange_rate', 15, 8)->default(1.00000000); // Rate relative to base currency
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
