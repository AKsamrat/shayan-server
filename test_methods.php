<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$methods = \App\Models\Order::select('payment_method')->distinct()->pluck('payment_method');
echo json_encode($methods, JSON_PRETTY_PRINT);
