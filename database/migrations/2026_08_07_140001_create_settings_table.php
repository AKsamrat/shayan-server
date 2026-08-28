<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->index();       // e.g. 'general', 'email', 'sms', 'topbar'
            $table->string('key')->unique();          // e.g. 'store_name', 'smtp_host'
            $table->longText('value')->nullable();     // JSON or plain string value
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
