<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ==================== GENERAL SETTINGS ====================
        $general = [
            'store_name'        => 'Shayan Mart',
            'store_email'       => 'support@shayanmart.com',
            'store_phone'       => '+880 1234-567890',
            'store_address'     => 'Dhaka, Bangladesh',
            'store_currency'    => 'BDT',
            'tax_rate'          => '5',
            'shipping_cost'     => '60',
            'free_shipping_min' => '1000',
        ];
        Setting::setGroup('general', $general);

        // ==================== EMAIL SETTINGS ====================
        $email = [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.mailtrap.io',
            'mail_port'         => '587',
            'mail_username'     => '',
            'mail_password'     => '',
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'noreply@shayanmart.com',
            'mail_from_name'    => 'Shayan Mart',
        ];
        Setting::setGroup('email', $email);

        // ==================== SMS SETTINGS ====================
        $sms = [
            'sms_provider'      => 'twilio',
            'sms_api_key'       => '',
            'sms_api_secret'    => '',
            'sms_sender_id'     => 'ShayanMart',
            'sms_from_number'   => '',
            'sms_enabled'       => false,
        ];
        Setting::setGroup('sms', $sms);

        // ==================== NOTIFICATION SETTINGS ====================
        $notifications = [
            'email_order_placed'       => true,
            'email_order_status'       => true,
            'email_shipping_update'    => true,
            'email_promotions'         => false,
            'email_newsletter'         => false,
            'sms_order_placed'         => false,
            'sms_order_status'         => false,
            'sms_shipping_update'      => false,
            'admin_new_order'          => true,
            'admin_low_stock'          => true,
            'admin_new_review'         => true,
            'admin_new_customer'       => true,
        ];
        Setting::setGroup('notifications', $notifications);

        // ==================== TOPBAR SETTINGS ====================
        $topbar = [
            'topbar_show_phone'     => true,
            'topbar_show_email'     => true,
            'topbar_show_address'   => false,
            'topbar_show_currency'  => true,
            'topbar_show_language'  => true,
        ];
        Setting::setGroup('topbar', $topbar);

        // ==================== REFERRAL SETTINGS ====================
        $referral = [
            'referral_points_reward' => 100, // Points awarded to referrer per successful registration
            'referral_enabled'       => true,
        ];
        Setting::setGroup('referral', $referral);
    }
}
