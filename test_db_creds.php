<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$partner = App\Models\DeliveryPartner::where('slug', 'pathao')->first();
echo "Sandbox mode: " . ($partner->is_sandbox ? 'YES' : 'NO') . "\n";
echo "Username: " . ($partner->config['username'] ?? 'null') . "\n";
echo "Client ID: " . ($partner->config['api_key'] ?? 'null') . "\n";
