<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the unique constraint from permission_actions.name
        Schema::table('permission_actions', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }

    public function down(): void
    {
        // Re-add the unique constraint (for rollback)
        Schema::table('permission_actions', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
