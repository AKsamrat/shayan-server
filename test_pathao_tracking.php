<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\PathaoCourierService::class);
$consignmentId = 'RY200925L8J5TL';
echo "Testing API endpoints for: $consignmentId\n";

try {
    echo "Running getOrderInfo:\n";
    $result = $service->getOrderInfo($consignmentId);
    echo print_r($result, true) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
