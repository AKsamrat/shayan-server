<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SteadfastCourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SteadfastController extends Controller
{
    protected SteadfastCourierService $steadfastService;

    public function __construct(SteadfastCourierService $steadfastService)
    {
        $this->steadfastService = $steadfastService;
    }

    /**
     * 1. Ping
     */
    public function ping(): JsonResponse
    {
        try {
            $data = $this->steadfastService->ping();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 2. Create Single Order
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice' => 'required|string|max:100',
            'recipient_name' => 'required|string|min:2|max:100',
            'recipient_phone' => 'required|string|min:11|max:14',
            'recipient_address' => 'required|string|min:5|max:255',
            'cod_amount' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $data = $this->steadfastService->createOrder($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. Bulk Order
     */
    public function bulkOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:500',
            'data.*.invoice' => 'required|string',
            'data.*.recipient_name' => 'required|string',
            'data.*.recipient_phone' => 'required|string',
            'data.*.recipient_address' => 'required|string',
            'data.*.cod_amount' => 'required|numeric',
            'data.*.note' => 'nullable|string',
        ]);

        try {
            $data = $this->steadfastService->bulkOrder($validated['data']);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 4. Bulk Order Extended
     */
    public function bulkOrderExtended(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|array|min:1|max:500',
            'data.*.invoice' => 'required|string',
            'data.*.recipient_name' => 'required|string',
            'data.*.recipient_phone' => 'required|string',
            'data.*.recipient_address' => 'required|string',
            'data.*.cod_amount' => 'required|numeric',
            'data.*.note' => 'nullable|string',
        ]);

        try {
            $data = $this->steadfastService->bulkOrderExtended($validated['data']);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 5. Status by Consignment ID
     */
    public function statusByCid($cid): JsonResponse
    {
        try {
            $data = $this->steadfastService->statusByCid($cid);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 6. Status with Return Status by Consignment ID
     */
    public function statusWithReturnByCid($cid): JsonResponse
    {
        try {
            $data = $this->steadfastService->statusWithReturnByCid($cid);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 7. Status by Invoice
     */
    public function statusByInvoice($invoice): JsonResponse
    {
        try {
            $data = $this->steadfastService->statusByInvoice($invoice);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 8. Status by Tracking Code
     */
    public function statusByTrackingCode($trackingCode): JsonResponse
    {
        try {
            $data = $this->steadfastService->statusByTrackingCode($trackingCode);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 9. Trackings by Invoice
     */
    public function trackingsByInvoice($invoice): JsonResponse
    {
        try {
            $data = $this->steadfastService->trackingsByInvoice($invoice);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 10. Create Pickup Request
     */
    public function createPickupRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|min:5|max:255',
            'contact_number' => 'required|string|min:11|max:14',
            'police_station_id' => 'nullable|integer',
            'address_id' => 'nullable|integer',
            'note' => 'nullable|string|max:255',
        ]);

        try {
            $data = $this->steadfastService->createPickupRequest($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 11. Create Return Request
     */
    public function createReturnRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'consignment_id' => 'nullable|string',
            'invoice' => 'nullable|string',
            'tracking_code' => 'nullable|string',
            'reason' => 'required|string|max:255',
        ]);

        if (empty($validated['consignment_id']) && empty($validated['invoice']) && empty($validated['tracking_code'])) {
            return response()->json([
                'error' => 'Please provide at least one identifier: Consignment ID, Invoice, or Tracking Code.'
            ], 422);
        }

        try {
            $data = $this->steadfastService->createReturnRequest($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 12. Get Return Requests
     */
    public function getReturnRequests(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        try {
            $data = $this->steadfastService->getReturnRequests($page);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 13. Get Return Request Details
     */
    public function getReturnRequest($id): JsonResponse
    {
        try {
            $data = $this->steadfastService->getReturnRequest($id);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 14. Get Balance
     */
    public function getBalance(): JsonResponse
    {
        try {
            $data = $this->steadfastService->getBalance();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 15. Get Payments
     */
    public function getPayments(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        try {
            $data = $this->steadfastService->getPayments($page);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 16. Get Payment Details
     */
    public function getPayment($paymentId): JsonResponse
    {
        try {
            $data = $this->steadfastService->getPayment($paymentId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 17. Get Police Stations
     */
    public function getPoliceStations(): JsonResponse
    {
        try {
            $data = $this->steadfastService->getPoliceStations();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 18. Fraud Check Score & History by Phone
     */
    public function getFraudScore(Request $request, $phone): JsonResponse
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '880')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        // 1. Local Store Orders History (checks shippingAddress, billingAddress, user)
        $localHistory = null;
        try {
            /** @var \App\Services\Courier\CourierFraudChecker $checker */
            $checker = app(\App\Services\Courier\CourierFraudChecker::class);
            $localHistory = $checker->localOverview($cleanPhone);
        } catch (\Exception $e) {
            Log::warning('Local fraud history lookup error: ' . $e->getMessage());
        }

        // 2. Courier Intelligence Network History (Pathao & CourierVerify)
        $courierNetwork = null;
        try {
            /** @var \App\Services\Courier\PathaoFraudCheckService $pathaoService */
            $pathaoService = app(\App\Services\Courier\PathaoFraudCheckService::class);
            $courierNetwork = $pathaoService->checkCustomer($cleanPhone);
        } catch (\Exception $e) {
            $courierNetwork = ['success' => false, 'message' => $e->getMessage()];
        }

        // 3. Direct Steadfast Courier API
        $steadfastData = null;
        $steadfastError = null;
        try {
            $steadfastData = $this->steadfastService->getFraudScore($cleanPhone);
        } catch (\Exception $e) {
            $steadfastError = $e->getMessage();
        }

        $steadfastNote = (($steadfastData['status'] ?? null) === 403)
            ? 'Steadfast Fraud Check Add-on is not active on this merchant account. Contact Steadfast Courier Support (support@steadfast.com.bd) to enable the Fraud Check API.'
            : null;

        return response()->json([
            'phone' => $phone,
            'clean_phone' => $cleanPhone,
            'courier_network' => $courierNetwork,
            'steadfast' => $steadfastData,
            'steadfast_error' => $steadfastError,
            'steadfast_note' => $steadfastNote,
            'local_history' => $localHistory ?? [
                'total_orders' => 0,
                'delivered' => 0,
                'cancelled' => 0,
                'pending' => 0,
                'success_rate' => null,
                'total_spent' => 0,
                'linked_user' => null,
                'linked_supplier' => null,
                'recent_orders' => [],
            ],
        ]);
    }
}
