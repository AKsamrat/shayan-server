<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$p = \App\Models\DeliveryPartner::where('slug', 'pathao')->first();
echo json_encode($p->config, JSON_PRETTY_PRINT);
