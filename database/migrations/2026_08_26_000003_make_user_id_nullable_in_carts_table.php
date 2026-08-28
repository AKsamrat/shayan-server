<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Get the database connection
        $connection = Schema::getConnection();
        $grammar = $connection->getSchemaGrammar();
        
        // Check if we're using MySQL
        if ($connection->getDriverName() === 'mysql') {
            // Use raw SQL for MySQL to avoid issues with change() method
            DB::statement('ALTER TABLE carts DROP FOREIGN KEY carts_user_id_foreign');
            DB::statement('ALTER TABLE carts MODIFY user_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE carts ADD CONSTRAINT carts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        } else {
            // For other databases, use the standard approach
            Schema::table('carts', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        
        if ($connection->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE carts DROP FOREIGN KEY carts_user_id_foreign');
            DB::statement('ALTER TABLE carts MODIFY user_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE carts ADD CONSTRAINT carts_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        } else {
            Schema::table('carts', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
