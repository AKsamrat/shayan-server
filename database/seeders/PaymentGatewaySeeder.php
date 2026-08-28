<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentGateway;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        // Bangladeshi gateways are listed first — this is a BDT storefront, so
        // bKash / Nagad / SSLCommerz are the primary methods. Config keys below
        // MUST match the field definitions in the admin UI (AdminPayments.tsx →
        // GATEWAY_CONFIGS) so saved credentials map to the right inputs.
        $gateways = [
            [
                'name' => 'bKash',
                'slug' => 'bkash',
                'description' => 'Accept bKash mobile payments from your customers.',
                'config' => [
                    'app_key' => '',
                    'app_secret' => '',
                    'username' => '',
                    'password' => '',
                    'sandbox' => 'true',
                    'callback_url' => '',
                    'success_url' => '',
                    'fail_url' => '',
                    'cancel_url' => '',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Nagad',
                'slug' => 'nagad',
                'description' => 'Accept Nagad digital payments from your customers.',
                'config' => [
                    'public_key' => '',
                    'private_key' => '',
                    'merchant_id' => '',
                    'sandbox' => 'true',
                    'api_url' => '',
                    'callback_url' => '',
                    'success_url' => '',
                    'fail_url' => '',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'SSL Commerz',
                'slug' => 'sslcommerz',
                'description' => 'Accept cards, mobile banking & internet banking via SSLCommerz.',
                'config' => [
                    'store_id' => '',
                    'store_password' => '',
                    'sandbox' => 'true',
                    'api_url' => '',
                    'validation_api' => '',
                    'currency' => 'BDT',
                    'payment_url' => '',
                    'success_url' => '',
                    'fail_url' => '',
                    'cancel_url' => '',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Stripe',
                'slug' => 'stripe',
                'description' => 'Accept payments via credit/debit cards through Stripe.',
                'config' => [
                    'publishable_key' => '',
                    'secret_key' => '',
                    'webhook_secret' => '',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'PayPal',
                'slug' => 'paypal',
                'description' => 'Accept payments through PayPal.',
                'config' => [
                    'client_id' => '',
                    'client_secret' => '',
                    'mode' => 'sandbox',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Cash on Delivery',
                'slug' => 'cash_on_delivery',
                'description' => 'Allow customers to pay when the order is delivered.',
                'config' => [
                    'instructions' => 'Pay the delivery person upon receiving your order.',
                ],
                'status' => 'active',
                'test_mode' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'Bank Transfer',
                'slug' => 'bank_transfer',
                'description' => 'Accept manual bank transfers.',
                'config' => [
                    'bank_name' => '',
                    'account_name' => '',
                    'account_number' => '',
                    'routing_number' => '',
                    'instructions' => 'Transfer the total amount to the bank account above.',
                ],
                'status' => 'inactive',
                'test_mode' => false,
                'sort_order' => 7,
            ],
            [
                'name' => 'Razorpay',
                'slug' => 'razorpay',
                'description' => 'Accept payments via Razorpay (UPI, Cards, Netbanking).',
                'config' => [
                    'key_id' => '',
                    'key_secret' => '',
                ],
                'status' => 'inactive',
                'test_mode' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($gateways as $gateway) {
            $existing = PaymentGateway::where('slug', $gateway['slug'])->first();

            if ($existing) {
                // Preserve any admin-entered credentials/status on re-seed;
                // only refresh presentational metadata (name, description, order).
                $existing->update([
                    'name' => $gateway['name'],
                    'description' => $gateway['description'],
                    'sort_order' => $gateway['sort_order'],
                ]);
            } else {
                PaymentGateway::create($gateway);
            }
        }
    }
}
