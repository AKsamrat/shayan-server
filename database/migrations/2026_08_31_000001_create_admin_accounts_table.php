<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('account_type', ['cash', 'bank', 'mobile_banking', 'credit_card', 'digital_wallet', 'other'])->default('cash');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('routing_number')->nullable();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_accounts');
    }
};
