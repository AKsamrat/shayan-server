<?php

namespace App\Services;

use App\Models\DeliveryPartner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastCourierService
{
    protected string $baseUrl;
    protected ?string $apiKey = null;
    protected ?string $secretKey = null;

    public function __construct()
    {
        $partner = DeliveryPartner::where('slug', 'steadfast')->first();
        if ($partner && $partner->config) {
            $this->apiKey = $partner->config['api_key'] ?? null;
            $this->secretKey = $partner->config['api_secret'] ?? null;
            
            $configuredUrl = $partner->config['base_url'] ?? '';
            // If configured URL contains obsolete or unreachable portal.steadfast.com.bd, use active portal.packzy.com
            if (empty($configuredUrl) || str_contains($configuredUrl, 'portal.steadfast.com.bd')) {
                $this->baseUrl = 'https://portal.packzy.com/api/v1';
            } else {
                $this->baseUrl = rtrim($configuredUrl, '/');
            }
        } else {
            $this->baseUrl = 'https://portal.packzy.com/api/v1';
        }
    }

    /**
     * Get headers for authenticated requests
     */
    protected function getHeaders(): array
    {
        return [
            'Api-Key' => $this->apiKey,
            'Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Helper to perform HTTP request with URL fallback if necessary
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $headers = $this->getHeaders();

        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->when($query, fn($req) => $req->withQueryParameters($query))
                ->send($method, $url, [
                    'json' => !empty($data) ? $data : null,
                ]);

            // If request failed because of DNS or host resolution, retry with packzy.com
            if (!$response->successful() && str_contains($this->baseUrl, 'steadfast.com.bd')) {
                $fallbackUrl = 'https://portal.packzy.com/api/v1/' . ltrim($endpoint, '/');
                $response = Http::withHeaders($headers)
                    ->timeout(20)
                    ->when($query, fn($req) => $req->withQueryParameters($query))
                    ->send($method, $fallbackUrl, [
                        'json' => !empty($data) ? $data : null,
                    ]);
            }

            return $response->json() ?? ['status' => $response->status(), 'body' => $response->body()];
        } catch (\Exception $e) {
            // If DNS failure or connection error, try portal.packzy.com
            if (!str_contains($this->baseUrl, 'packzy.com')) {
                try {
                    $fallbackUrl = 'https://portal.packzy.com/api/v1/' . ltrim($endpoint, '/');
                    $response = Http::withHeaders($headers)
                        ->timeout(20)
                        ->when($query, fn($req) => $req->withQueryParameters($query))
                        ->send($method, $fallbackUrl, [
                            'json' => !empty($data) ? $data : null,
                        ]);
                    return $response->json() ?? ['status' => $response->status(), 'body' => $response->body()];
                } catch (\Exception $fallbackEx) {
                    Log::error('Steadfast API Fallback Error', ['endpoint' => $endpoint, 'error' => $fallbackEx->getMessage()]);
                }
            }

            Log::error('Steadfast API Request Error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            throw new \Exception('Steadfast API Error: ' . $e->getMessage());
        }
    }

    /**
     * 1. Ping: Check API connectivity without API Key
     */
    public function ping(): array
    {
        try {
            $url = $this->baseUrl . '/ping';
            $response = Http::timeout(10)->acceptJson()->get($url);
            if ($response->successful()) {
                return $response->json();
            }
            // fallback
            $fallback = Http::timeout(10)->acceptJson()->get('https://portal.packzy.com/api/v1/ping');
            return $fallback->json() ?? ['status' => $fallback->status(), 'message' => $fallback->body()];
        } catch (\Exception $e) {
            try {
                $fallback = Http::timeout(10)->acceptJson()->get('https://portal.packzy.com/api/v1/ping');
                return $fallback->json() ?? ['status' => $fallback->status(), 'message' => $fallback->body()];
            } catch (\Exception $ex) {
                return ['status' => 500, 'message' => $ex->getMessage()];
            }
        }
    }

    /**
     * 2. Create Order: Book one parcel
     */
    public function createOrder(array $data): array
    {
        return $this->request('POST', 'create_order', [
            'invoice' => (string) ($data['invoice'] ?? ''),
            'recipient_name' => $data['recipient_name'],
            'recipient_phone' => $data['recipient_phone'],
            'recipient_address' => $data['recipient_address'],
            'cod_amount' => (float) ($data['cod_amount'] ?? 0),
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * 3. Bulk Order: Book up to 500 parcels
     */
    public function bulkOrder(array $orders): array
    {
        return $this->request('POST', 'create_order/bulk-order', [
            'data' => $orders,
        ]);
    }

    /**
     * 4. Bulk Order Extended: Book with per-field validation messages
     */
    public function bulkOrderExtended(array $orders): array
    {
        return $this->request('POST', 'create_order/bulk-order/extended', [
            'data' => $orders,
        ]);
    }

    /**
     * 5. Status by Consignment ID
     */
    public function statusByCid($consignmentId): array
    {
        return $this->request('GET', "status_by_cid/{$consignmentId}");
    }

    /**
     * 6. Status with Return Status by Consignment ID
     */
    public function statusWithReturnByCid($consignmentId): array
    {
        return $this->request('GET', "status_with_return_status_by_cid/{$consignmentId}");
    }

    /**
     * 7. Status by Invoice
     */
    public function statusByInvoice(string $invoice): array
    {
        return $this->request('GET', "status_by_invoice/{$invoice}");
    }

    /**
     * 8. Status by Tracking Code
     */
    public function statusByTrackingCode(string $trackingCode): array
    {
        return $this->request('GET', "status_by_trackingcode/{$trackingCode}");
    }

    /**
     * 9. Trackings by Invoice: Full journey history
     */
    public function trackingsByInvoice(string $invoice): array
    {
        return $this->request('GET', "trackings_by_invoice/{$invoice}");
    }

    /**
     * 10. Create Pickup Request
     */
    public function createPickupRequest(array $data): array
    {
        $payload = [
            'address' => $data['address'] ?? ($data['pickup_address'] ?? ''),
            'contact_number' => $data['contact_number'] ?? ($data['phone'] ?? ''),
            'police_station_id' => $data['police_station_id'] ?? null,
            'address_id' => $data['address_id'] ?? null,
            'note' => $data['note'] ?? null,
        ];

        // Clean out nulls if optional
        if (is_null($payload['police_station_id'])) unset($payload['police_station_id']);
        if (is_null($payload['address_id'])) unset($payload['address_id']);

        return $this->request('POST', 'create_pickup_request', $payload);
    }

    /**
     * 11. Create Return Request
     */
    public function createReturnRequest(array $data): array
    {
        $payload = [
            'reason' => $data['reason'] ?? 'Return requested by merchant',
        ];

        if (!empty($data['consignment_id'])) {
            $payload['consignment_id'] = $data['consignment_id'];
        }
        if (!empty($data['invoice'])) {
            $payload['invoice'] = $data['invoice'];
        }
        if (!empty($data['tracking_code'])) {
            $payload['tracking_code'] = $data['tracking_code'];
        }

        return $this->request('POST', 'create_return_request', $payload);
    }

    /**
     * 12. Get Return Requests (Paginated)
     */
    public function getReturnRequests(int $page = 1): array
    {
        return $this->request('GET', 'get_return_requests', [], ['page' => $page]);
    }

    /**
     * 13. Get Single Return Request
     */
    public function getReturnRequest($id): array
    {
        return $this->request('GET', "get_return_request/{$id}");
    }

    /**
     * 14. Get Balance: What Steadfast currently owes the merchant
     */
    public function getBalance(): array
    {
        return $this->request('GET', 'get_balance');
    }

    /**
     * 15. Get Payments / Payouts (Paginated)
     */
    public function getPayments(int $page = 1): array
    {
        return $this->request('GET', 'payments', [], ['page' => $page]);
    }

    /**
     * 16. Get Single Payment details
     */
    public function getPayment($paymentId): array
    {
        return $this->request('GET', "payments/{$paymentId}");
    }

    /**
     * 17. Get Police Stations (Thana & Districts)
     */
    public function getPoliceStations(): array
    {
        return $this->request('GET', 'police_stations');
    }

    /**
     * 18. Fraud Check Score by Phone
     */
    public function getFraudScore(string $phone): array
    {
        // Sanitize phone: 11 digits
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '880')) {
            $cleanPhone = substr($cleanPhone, 2);
        }

        return $this->request('GET', "fraud_check/score/{$cleanPhone}");
    }
}
