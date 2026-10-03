<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = App\Models\Order::whereHas('deliveryBooking')->with('deliveryBooking')->get();
foreach(\ as \) {
    echo \->id . ' - ' . \->deliveryBooking->partner . ' - ' . \->deliveryBooking->tracking_id . PHP_EOL;
}
