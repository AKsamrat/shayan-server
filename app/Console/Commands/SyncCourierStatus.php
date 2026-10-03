<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use App\Services\SteadfastCourierService;
use App\Services\PathaoCourierService;

class SyncCourierStatus extends Command
{
    protected $signature = 'courier:sync-status';
    protected $description = 'Sync order statuses from couriers (Pathao/Steadfast)';

    public function handle()
    {
        \->info('Starting courier status sync...');
        
        \ = Order::whereHas('deliveryBooking')
            ->with('deliveryBooking')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->get();

        \ = 0;

        foreach (\ as \) {
            try {
                \ = \->deliveryBooking;
                \ = \->partner ?? null;
                \ = \->tracking_id ?? null;
                
                if (!\ || !\) continue;

                \ = null;
                \ = null;
                
                if (\ === 'steadfast') {
                    \ = app(SteadfastCourierService::class);
                    \ = \->statusByCid(\);
                    if (isset(\['status'])) {
                        \ = strtolower(\['status']);
                        if (str_contains(\, 'delivered')) { \ = 'delivered'; \ = 'paid'; }
                        elseif (str_contains(\, 'return')) { \ = 'returned'; }
                        elseif (str_contains(\, 'cancel')) { \ = 'cancelled'; }
                        else { \ = 'shipped'; }
                    }
                } elseif (\ === 'pathao') {
                    \ = app(PathaoCourierService::class);
                    \ = \->getOrderInfo(\);
                    if (isset(\['data']['order_status'])) {
                        \ = strtolower(\['data']['order_status']);
                        if (str_contains(\, 'delivered') || str_contains(\, 'successful')) { \ = 'delivered'; \ = 'paid'; }
                        elseif (str_contains(\, 'return')) { \ = 'returned'; }
                        elseif (str_contains(\, 'cancel')) { \ = 'cancelled'; }
                        else { \ = 'shipped'; }
                    }
                }

                \ = false;
                if (\ && \ !== \->status) {
                    \->status = \;
                    \ = true;
                }
                
                if (\ && \ !== \->payment_status) {
                    \->payment_status = \;
                    \ = true;
                }

                if (\) {
                    \->save();
                    \->status = \;
                    \->save();
                    \++;
                    \->info("Order \->id updated to \");
                }
            } catch (\Exception \) {
                Log::error("Failed to sync order {\->id}: " . \->getMessage());
                \->error("Failed to sync order {\->id}: " . \->getMessage());
            }
        }

        \->info("Sync complete. Updated \ orders.");
    }
}
