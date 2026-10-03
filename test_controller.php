<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(\App\Http\Controllers\Api\AdminController::class);
$request = \Illuminate\Http\Request::create('/api/admin/reviews/1/status', 'PUT', ['is_approved' => true]);
try {
    $response = $controller->updateReviewStatus($request, 1);
    echo "SUCCESS\n";
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
