<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$orders = \App\Models\Order::select('payment_method', 'payment_status')->take(5)->get();
echo json_encode($orders, JSON_PRETTY_PRINT);
