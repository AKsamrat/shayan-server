<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentTransaction;
use App\Models\Order;

class PaymentTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::all();

        if ($orders->isEmpty()) {
            return;
        }

        $gateways = ['stripe', 'paypal', 'cash_on_delivery', 'bank_transfer'];
        $statuses = ['completed', 'completed', 'completed', 'pending', 'failed'];

        foreach ($orders as $index => $order) {
            PaymentTransaction::updateOrCreate(
                [
                    'order_id' => $order->id,
                ],
                [
                    'order_number' => $order->order_number ?? "ORD-" . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                    'gateway' => $gateways[array_rand($gateways)],
                    'method' => 'card',
                    'amount' => $order->total,
                    'currency' => 'USD',
                    'status' => $statuses[array_rand($statuses)],
                    'transaction_id' => 'txn_' . bin2hex(random_bytes(8)),
                    'gateway_response' => json_encode(['status' => 'success', 'message' => 'Payment processed']),
                ]
            );
        }
    }
}
