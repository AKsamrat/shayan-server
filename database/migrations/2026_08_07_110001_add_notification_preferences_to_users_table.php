<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Master toggles
            $table->boolean('email_notifications')->default(true)->after('two_factor_enabled');
            $table->boolean('sms_notifications')->default(true)->after('email_notifications');
            $table->boolean('push_notifications')->default(true)->after('sms_notifications');

            // Granular email preferences
            $table->boolean('email_order_updates')->default(true)->after('push_notifications');
            $table->boolean('email_shipping_updates')->default(true)->after('email_order_updates');
            $table->boolean('email_promotions')->default(false)->after('email_shipping_updates');
            $table->boolean('email_newsletter')->default(false)->after('email_promotions');

            // Granular SMS preferences
            $table->boolean('sms_order_updates')->default(true)->after('email_newsletter');
            $table->boolean('sms_shipping_updates')->default(true)->after('sms_order_updates');
            $table->boolean('sms_promotions')->default(false)->after('sms_shipping_updates');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_notifications',
                'sms_notifications',
                'push_notifications',
                'email_order_updates',
                'email_shipping_updates',
                'email_promotions',
                'email_newsletter',
                'sms_order_updates',
                'sms_shipping_updates',
                'sms_promotions',
            ]);
        });
    }
};
