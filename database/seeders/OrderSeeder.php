<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Get customers and products
        $customers = User::where('role', 'customer')->get();
        $products = Product::inRandomOrder()->limit(20)->get();

        if ($customers->isEmpty() || $products->isEmpty()) {
            return;
        }

        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned', 'refunded'];
        $paymentMethods = ['cash_on_delivery', 'credit_card', 'online_bank_transfer', 'mobile_payment'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        // Create orders for the past 30 days
        for ($i = 0; $i < 50; $i++) {
            $customer = $customers->random();
            $status = $statuses[array_rand($statuses)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            $paymentStatus = $status === 'cancelled' ? 'pending' : $paymentStatuses[array_rand($paymentStatuses)];
            
            // Random date in the past 30 days
            $createdAt = now()->subDays(rand(0, 29))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            // Create shipping address
            $shippingAddress = Address::create([
                'user_id' => $customer->id,
                'type' => 'shipping',
                'full_name' => $customer->name,
                'phone' => $customer->phone ?? '+88017' . rand(10000000, 99999999),
                'email' => $customer->email,
                'address_line_1' => rand(1, 100) . ' Street, Dhaka',
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'postal_code' => '1000' . rand(1, 9),
                'country' => 'Bangladesh',
            ]);

            // Create billing address
            $billingAddress = Address::create([
                'user_id' => $customer->id,
                'type' => 'billing',
                'full_name' => $customer->name,
                'phone' => $customer->phone ?? '+88017' . rand(10000000, 99999999),
                'email' => $customer->email,
                'address_line_1' => rand(1, 100) . ' Avenue, Dhaka',
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'postal_code' => '1000' . rand(1, 9),
                'country' => 'Bangladesh',
            ]);

            // Calculate subtotal first by preparing order items
            $itemCount = rand(1, 3);
            $selectedProducts = $products->random($itemCount);
            $subtotal = 0;
            $orderItems = [];

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 3);
                $price = rand(100, 5000);
                $itemTotal = $quantity * $price;
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $itemTotal,
                ];
            }

            $tax = rand(50, 500);
            $shipping = rand(50, 200);
            $discount = rand(0, 300);

            $order = Order::create([
                'user_id' => $customer->id,
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'status' => $status,
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress->id,
                'subtotal' => $subtotal,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'notes' => $i % 5 === 0 ? 'Please deliver after 5 PM' : null,
                'tax' => $tax,
                'shipping_cost' => $shipping,
                'discount' => $discount,
                'total' => $subtotal + $tax + $shipping - $discount,
                'transaction_id' => 'TXN-' . uniqid(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    ...$item,
                ]);
            }
        }
    }
}
