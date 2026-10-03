<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\Api\CourierPanelController();
$request = Illuminate\Http\Request::create('/api/admin/courier-panel', 'GET');
$response = $controller->index($request);
echo json_encode($response->getData(true));
