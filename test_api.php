<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$req = Illuminate\Http\Request::create('/api/admin/reviews/1/status', 'PUT', ['is_approved' => true]);
$req->headers->set('Accept', 'application/json');

// create a dummy admin user if none exists for testing, or just use ID 1
$u = App\Models\User::where('role', 'admin')->first() ?: App\Models\User::first();
$req->setUserResolver(function() use ($u) { return $u; });

$res = $kernel->handle($req);
echo "STATUS: " . $res->getStatusCode() . "\n";
echo "CONTENT: " . $res->getContent() . "\n";
