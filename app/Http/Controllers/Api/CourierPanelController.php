<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\DeliveryBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SteadfastCourierService;
use App\Services\PathaoCourierService;

class CourierPanelController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::whereHas('deliveryBooking')
            ->with(['user', 'shippingAddress', 'deliveryBooking'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $stats = [
            'total_booked' => 0,
            'delivered' => 0,
            'in_transit' => 0,
            'returned' => 0,
            'collected_amount' => 0,
        ];
        
        $ordersByPartner = [];

        foreach ($orders as $order) {
            $booking = $order->deliveryBooking;
            $partner = $booking->partner ?? 'unknown';
            
            if (!isset($ordersByPartner[$partner])) {
                $ordersByPartner[$partner] = [];
            }
            
            // Append booking as array for the frontend compatibility
            $orderData = $order->toArray();
            $orderData['delivery_booking'] = $booking->toArray();
            
            $ordersByPartner[$partner][] = $orderData;
            
            $stats['total_booked']++;
            
            if ($order->status === 'delivered') {
                $stats['delivered']++;
                if ($order->payment_status === 'paid') {
                    $stats['collected_amount'] += $order->total;
                }
            } elseif ($order->status === 'returned') {
                $stats['returned']++;
            } else {
                $stats['in_transit']++;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => $stats,
                'orders_by_partner' => $ordersByPartner
            ]
        ]);
    }

    public function syncStatuses(Request $request)
    {
        $activeOrders = Order::whereHas('deliveryBooking')
            ->with('deliveryBooking')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->get();

        $updatedCount = 0;

        foreach ($activeOrders as $order) {
            try {
                $booking = $order->deliveryBooking;
                $partner = $booking->partner ?? null;
                $consignmentId = $booking->tracking_id ?? null;
                
                if (!$partner || !$consignmentId) continue;

                $newStatus = null;
                $newPaymentStatus = null;
                
                if ($partner === 'steadfast') {
                    $sf = app(SteadfastCourierService::class);
                    $res = $sf->statusByCid($consignmentId);
                    if (isset($res['status'])) {
                        $s = strtolower($res['status']);
                        if (str_contains($s, 'delivered')) { $newStatus = 'delivered'; $newPaymentStatus = 'paid'; }
                        elseif (str_contains($s, 'return')) { $newStatus = 'returned'; }
                        elseif (str_contains($s, 'cancel')) { $newStatus = 'cancelled'; }
                        else { $newStatus = 'shipped'; }
                    }
                } elseif ($partner === 'pathao') {
                    $pathao = app(PathaoCourierService::class);
                    $res = $pathao->getOrderInfo($consignmentId);
                    if (isset($res['data']['order_status'])) {
                        $s = strtolower($res['data']['order_status']);
                        if (str_contains($s, 'delivered') || str_contains($s, 'successful')) { $newStatus = 'delivered'; $newPaymentStatus = 'paid'; }
                        elseif (str_contains($s, 'return')) { $newStatus = 'returned'; }
                        elseif (str_contains($s, 'cancel')) { $newStatus = 'cancelled'; }
                        else { $newStatus = 'shipped'; }
                    }
                }

                $changed = false;
                if ($newStatus && $newStatus !== $order->status) {
                    $order->status = $newStatus;
                    $changed = true;
                }
                
                if ($newPaymentStatus && $newPaymentStatus !== $order->payment_status) {
                    $order->payment_status = $newPaymentStatus;
                    $changed = true;
                }

                if ($changed) {
                    $order->save();
                    $booking->status = $newStatus;
                    $booking->save();
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync order {$order->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Synced $updatedCount orders.",
            'updated_count' => $updatedCount
        ]);
    }
}
