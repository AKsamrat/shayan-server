<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$p = app('App\Services\PathaoCourierService');
$res = $p->createOrder([
    'store_id' => 158408,
    'merchant_order_id' => 'ORD-1235',
    'recipient_name' => 'John',
    'recipient_phone' => '01712345678',
    'recipient_address' => 'Test Address, Dhaka',
    'delivery_type' => 48,
    'item_type' => 2,
    'item_quantity' => 1,
    'item_weight' => 0.5,
    'amount_to_collect' => 100
]);
echo json_encode($res, JSON_PRETTY_PRINT);
