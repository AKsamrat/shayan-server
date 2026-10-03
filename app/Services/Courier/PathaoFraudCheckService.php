<?php

namespace App\Services\Courier;

use App\Models\DeliveryPartner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PathaoFraudCheckService
 *
 * Dedicated service for checking customer delivery and cancellation history
 * with Pathao Courier before fulfilling or accepting Cash-on-Delivery (COD) orders.
 *
 * NOTE: The customer-history check is part of the Pathao Merchant Panel and uses
 * an internal/private endpoint (`/api/v1/user/success`) rather than the public
 * open Aladdin API (`/aladdin/api/v1/*`). This endpoint is isolated within this service.
 */
class PathaoFraudCheckService
{
    /**
     * Pathao Merchant Panel Private Endpoint for Customer History/Fraud Check
     */
    public const PRIVATE_CUSTOMER_HISTORY_ENDPOINT = '/api/v1/user/success';

    /**
     * Pathao Merchant Panel Login Endpoint
     */
    public const PRIVATE_MERCHANT_LOGIN_ENDPOINT = '/api/v1/login';

    /**
     * Public OAuth Token Endpoint (Fallback)
     */
    public const PUBLIC_OAUTH_TOKEN_ENDPOINT = '/aladdin/api/v1/issue-token';

    protected string $baseUrl;
    protected string $merchantPanelUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $username;
    protected ?string $password;
    protected ?string $merchantId;
    protected bool $sandbox;
    protected int $timeout;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load Pathao credentials, prioritizing the database delivery partner settings
     * with fallback to config('fraud_check.pathao') and environment variables.
     */
    protected function loadConfiguration(): void
    {
        $config = [];
        $isPartnerSandbox = null;

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('delivery_partners')) {
                $partner = DeliveryPartner::where('slug', 'pathao')->first();
                if ($partner) {
                    $config = $partner->config ?? [];
                    $isPartnerSandbox = $partner->is_sandbox;
                }
            }
        } catch (\Throwable) {
            // Degrade to config/env if database query fails or during unit tests
        }

        $this->sandbox = (bool) ($isPartnerSandbox ?? config('fraud_check.pathao.sandbox', false));
        
        $defaultBaseUrl = $this->sandbox
            ? 'https://courier-api-sandbox.pathao.com'
            : 'https://api-hermes.pathao.com';

        $this->baseUrl = rtrim($config['base_url'] ?? config('fraud_check.pathao.base_url', $defaultBaseUrl), '/');
        $this->merchantPanelUrl = rtrim(config('fraud_check.pathao.merchant_panel_url', 'https://merchant.pathao.com'), '/');

        $this->clientId     = $config['api_key'] ?? $config['client_id'] ?? config('fraud_check.pathao.client_id');
        $this->clientSecret = $config['api_secret'] ?? $config['client_secret'] ?? config('fraud_check.pathao.client_secret');
        $this->username     = $config['username'] ?? config('fraud_check.pathao.username', 'mock_user');
        $this->password     = $config['password'] ?? config('fraud_check.pathao.password', 'mock_pass');
        $this->merchantId   = $config['merchant_id'] ?? config('fraud_check.pathao.merchant_id');
        $this->timeout      = (int) config('fraud_check.timeout', 15);
    }

    /**
     * Normalize Bangladeshi phone number into standard 11-digit format (e.g. 01712345678).
     * Handles +880, 880, dashes, spaces, and leading zero omissions.
     */
    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // If starts with 8801... (13 digits), strip 88
        if (str_starts_with($digits, '8801') && strlen($digits) === 13) {
            $digits = substr($digits, 2);
        }

        // If starts with 1... (10 digits), prepend 0
        if (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        // Must be an 11-digit Bangladeshi mobile number starting with 01[3-9]
        if (preg_match('/^01[3-9]\d{8}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    /**
     * Get or refresh Pathao access token with caching.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws \Exception
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'pathao_merchant_panel_auth_token';

        if (!$forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        if (empty($this->username) || empty($this->password)) {
            throw new \Exception('Pathao username or password not configured in delivery partner settings or environment.', 401);
        }

        $token = null;
        $expiresIn = 86400; // default 24h

        // Attempt 1: Direct Merchant Panel Login (where /api/v1/user/success resides)
        try {
            $response = Http::timeout($this->timeout)->post("{$this->merchantPanelUrl}" . self::PRIVATE_MERCHANT_LOGIN_ENDPOINT, [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'] ?? null;
                $expiresIn = (int) ($data['expires_in'] ?? 86400);
            }
        } catch (\Throwable $e) {
            Log::warning('Pathao merchant panel login error', ['error' => $e->getMessage()]);
        }

        // Attempt 2: Public OAuth Token endpoint fallback
        if (!$token && !empty($this->clientId) && !empty($this->clientSecret)) {
            try {
                $response = Http::timeout($this->timeout)->post("{$this->baseUrl}" . self::PUBLIC_OAUTH_TOKEN_ENDPOINT, [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type'    => 'password',
                    'username'      => $this->username,
                    'password'      => $this->password,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $token = $data['access_token'] ?? null;
                    $expiresIn = (int) ($data['expires_in'] ?? 86400);
                }
            } catch (\Throwable $e) {
                Log::warning('Pathao OAuth issue-token error', ['error' => $e->getMessage()]);
            }
        }

        if (!$token) {
            throw new \Exception('Failed to authenticate with Pathao. Please verify username and password.', 401);
        }

        // Cache token with a 5-minute safety buffer
        $ttl = max(60, $expiresIn - 300);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Check customer delivery history and fraud statistics.
     *
     * @param string $phone
     * @param bool $forceRefresh
     * @return array
     */
    public function checkCustomer(string $phone, bool $forceRefresh = false): array
    {
        $startTime = microtime(true);
        $normalizedPhone = $this->normalizePhone($phone);

        if (!$normalizedPhone) {
            return [
                'success' => false,
                'error'   => 'INVALID_PHONE',
                'message' => 'Invalid Bangladeshi phone number. Must be 11 digits starting with 01 (e.g., 01712345678).',
            ];
        }

        $maskedPhone = substr($normalizedPhone, 0, 3) . '****' . substr($normalizedPhone, -4);
        $cacheKey = "pathao_fraud_check:{$normalizedPhone}";

        // Return cached result if available and force refresh is not requested
        if (!$forceRefresh && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        try {
            $token = $this->getAccessToken($forceRefresh);
        } catch (\Throwable $e) {
            $this->logCheck($maskedPhone, 0, false, 401, 'PATHAO_AUTH_FAILED');
            return [
                'success' => false,
                'error'   => 'PATHAO_AUTH_FAILED',
                'message' => 'Pathao authentication failed. Please check Pathao credentials in settings.',
            ];
        }

        $endpointUrl = "{$this->merchantPanelUrl}" . self::PRIVATE_CUSTOMER_HISTORY_ENDPOINT;

        try {
            $response = $this->sendStatsRequest($endpointUrl, $token, $normalizedPhone);

            // If 401 Unauthorized, refresh token once and retry
            if ($response->status() === 401) {
                Cache::forget('pathao_merchant_panel_auth_token');
                $token = $this->getAccessToken(true);
                $response = $this->sendStatsRequest($endpointUrl, $token, $normalizedPhone);
            }

            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($response->status() === 429) {
                $this->logCheck($maskedPhone, $durationMs, false, 429, 'PATHAO_RATE_LIMITED');
                return [
                    'success' => false,
                    'error'   => 'PATHAO_RATE_LIMITED',
                    'message' => 'Pathao rate limit exceeded. Please wait a moment before trying again.',
                ];
            }

            if ($response->status() === 404) {
                // Customer has no record in Pathao
                $result = $this->formatNotFoundResponse($normalizedPhone);
                Cache::put($cacheKey, $result, (int) config('fraud_check.cache_ttl', 1800));
                $this->logCheck($maskedPhone, $durationMs, true, 404, null);
                return $result;
            }

            if (!$response->successful()) {
                $this->logCheck($maskedPhone, $durationMs, false, $response->status(), 'PATHAO_UNAVAILABLE');
                return [
                    'success' => false,
                    'error'   => 'PATHAO_UNAVAILABLE',
                    'message' => "Pathao customer history could not be checked at this time (HTTP {$response->status()}).",
                ];
            }

            $body = $response->json();
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            $customer = is_array($data['customer'] ?? null) ? $data['customer'] : null;

            // Extract numerical counts from v1 customer object or v2 data object if present
            $successful = null;
            $total = null;

            if ($customer !== null) {
                if (isset($customer['successful_delivery'])) {
                    $successful = (int) $customer['successful_delivery'];
                }
                if (isset($customer['total_delivery'])) {
                    $total = (int) $customer['total_delivery'];
                }
            } else {
                if (isset($data['successful_delivery'])) {
                    $successful = (int) $data['successful_delivery'];
                }
                if (isset($data['total_delivery'])) {
                    $total = (int) $data['total_delivery'];
                }
            }

            $cancelled = ($total !== null && $successful !== null) ? max(0, $total - $successful) : null;
            $successRate = ($total !== null && $total > 0 && $successful !== null)
                ? round(($successful / $total) * 100, 1)
                : null;

            // Extract Pathao v2 customer rating and display settings
            $rawRating = $data['customer_rating'] ?? ($customer['customer_rating'] ?? null);
            $showCount = isset($data['show_count']) ? (bool) $data['show_count'] : ($total !== null);
            $version   = $data['version'] ?? ($customer !== null ? 'v1' : 'v2');

            // If completely empty response with neither rating nor counts
            if (empty($data) && $customer === null) {
                $result = $this->formatNotFoundResponse($normalizedPhone);
                Cache::put($cacheKey, $result, (int) config('fraud_check.cache_ttl', 1800));
                $this->logCheck($maskedPhone, $durationMs, true, 200, null);
                return $result;
            }

            // Determine customer type and risk level
            [$customerType, $riskLevel] = $this->determineRatingAndRisk($rawRating, $total, $cancelled, $successRate);

            // Compute rating-based metrics for Pathao v2 where exact parcel counts are hidden
            $metrics = $this->getRatingMetrics($rawRating);

            // Descriptive message for admin / operator
            $message = $this->buildStatusMessage($rawRating, $showCount, $total, $customerType);

            $result = [
                'success'                => true,
                'phone'                  => $normalizedPhone,
                'courier'                => 'pathao',
                'total_orders'           => $total,
                'successful_orders'      => $successful,
                'cancelled_orders'       => $cancelled,
                'success_rate'           => $successRate ?? $metrics['estimated_success_rate'],
                'success_rate_range'     => $metrics['success_rate_range'],
                'delivery_reliability'   => $metrics['delivery_reliability'],
                'cancellation_risk'      => $metrics['cancellation_risk'],
                'tier_grade'             => $metrics['tier_grade'],
                'customer_type'          => $customerType,
                'risk_level'             => $riskLevel,
                'customer_rating'        => $rawRating,
                'show_count'             => $showCount,
                'version'                => $version,
                'raw_available'          => true,
                'message'                => $message,
            ];

            // Cache result by normalized phone number
            Cache::put($cacheKey, $result, (int) config('fraud_check.cache_ttl', 1800));

            $this->logCheck($maskedPhone, $durationMs, true, 200, null);
            return $result;

        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000);
            Log::error('Pathao fraud check network error', [
                'phone'       => $maskedPhone,
                'error'       => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return [
                'success' => false,
                'error'   => 'PATHAO_UNAVAILABLE',
                'message' => 'Pathao customer history could not be checked at this time.',
            ];
        }
    }

    /**
     * Send HTTP request to Pathao stats endpoint.
     */
    protected function sendStatsRequest(string $url, string $token, string $phone)
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])
            ->post($url, [
                'phone' => $phone,
            ]);
    }

    /**
     * Compute risk assessment based on configurable business rules.
     *
     * @param int|null $total
     * @param int|null $cancelled
     * @param float|null $successRate
     * @return string LOW | MEDIUM | HIGH | UNKNOWN
     */
    public function evaluateRiskLevel(?int $total, ?int $cancelled, ?float $successRate): string
    {
        if ($total === null || $total === 0 || $successRate === null) {
            return 'unknown';
        }

        $minOrders = (int) config('fraud_check.rules.min_orders_for_evaluation', 3);
        $highCancel = (float) config('fraud_check.rules.high_cancel_rate', 50.0);
        $mediumCancel = (float) config('fraud_check.rules.medium_cancel_rate', 25.0);

        if ($total < $minOrders) {
            return ($cancelled && $cancelled > 0) ? 'medium' : 'low';
        }

        $cancelRate = 100.0 - $successRate;

        if ($cancelRate >= $highCancel) {
            return 'high';
        }

        if ($cancelRate >= $mediumCancel) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Customer classification classification based on history.
     */
    public function evaluateCustomerType(?int $total, ?float $successRate): ?string
    {
        if ($total === null) {
            return null;
        }

        if ($total === 0) {
            return 'new_customer';
        }

        if ($successRate >= 85.0) {
            return 'excellent_customer';
        }

        if ($successRate >= 70.0) {
            return 'good_customer';
        }

        if ($successRate >= 50.0) {
            return 'moderate_customer';
        }

        return 'risky_customer';
    }

    /**
     * Determine customer classification and risk level based on Pathao rating and delivery counts.
     *
     * @param string|null $rawRating
     * @param int|null $total
     * @param int|null $cancelled
     * @param float|null $successRate
     * @return array [customerType, riskLevel]
     */
    public function determineRatingAndRisk(?string $rawRating, ?int $total, ?int $cancelled, ?float $successRate): array
    {
        if (!empty($rawRating)) {
            $normalized = strtolower(trim($rawRating));

            switch ($normalized) {
                case 'excellent_customer':
                case 'excellent':
                    return ['excellent_customer', 'low'];

                case 'good_customer':
                case 'good':
                    return ['good_customer', 'low'];

                case 'average_customer':
                case 'average':
                case 'moderate':
                case 'moderate_customer':
                    return ['average_customer', 'medium'];

                case 'bad_customer':
                case 'poor_customer':
                case 'bad':
                case 'poor':
                case 'high_risk':
                case 'fraud':
                    return ['high_risk_customer', 'high'];

                case 'new_customer':
                case 'new':
                    return ['new_customer', 'unknown'];

                default:
                    if (str_contains($normalized, 'excellent') || str_contains($normalized, 'good')) {
                        return [$normalized, 'low'];
                    }
                    if (str_contains($normalized, 'bad') || str_contains($normalized, 'risk') || str_contains($normalized, 'fraud')) {
                        return [$normalized, 'high'];
                    }
                    return [$normalized, 'medium'];
            }
        }

        $risk = $this->evaluateRiskLevel($total, $cancelled, $successRate);
        $type = $this->evaluateCustomerType($total, $successRate);

        return [$type, $risk];
    }

    /**
     * Build an informative status message for Pathao customer history.
     */
    public function buildStatusMessage(?string $rawRating, bool $showCount, ?int $total, ?string $customerType): ?string
    {
        if (!$showCount && !empty($rawRating) && $rawRating !== 'new_customer') {
            $ratingLabel = ucwords(str_replace('_', ' ', $rawRating));
            return "Pathao v2 rating: {$ratingLabel} (individual parcel counts are kept private by Pathao).";
        }

        if ($rawRating === 'new_customer' || ($total === 0 && $customerType === 'new_customer')) {
            return 'New customer on Pathao (no prior delivery history recorded).';
        }

        return null;
    }

    /**
     * Map Pathao v2 customer rating into estimated delivery rate and risk metrics.
     *
     * @param string|null $rawRating
     * @return array
     */
    public function getRatingMetrics(?string $rawRating): array
    {
        $normalized = strtolower(trim((string) $rawRating));

        switch ($normalized) {
            case 'excellent_customer':
            case 'excellent':
                return [
                    'estimated_success_rate' => 95.0,
                    'success_rate_range'     => '90% - 100%',
                    'delivery_reliability'   => 'Very High',
                    'cancellation_risk'      => 'Very Low (< 10%)',
                    'tier_grade'             => 'Tier A (Trusted)',
                ];

            case 'good_customer':
            case 'good':
                return [
                    'estimated_success_rate' => 82.0,
                    'success_rate_range'     => '75% - 89%',
                    'delivery_reliability'   => 'Good',
                    'cancellation_risk'      => 'Low (10% - 25%)',
                    'tier_grade'             => 'Tier B (Reliable)',
                ];

            case 'average_customer':
            case 'average':
            case 'moderate':
            case 'moderate_customer':
                return [
                    'estimated_success_rate' => 60.0,
                    'success_rate_range'     => '50% - 74%',
                    'delivery_reliability'   => 'Moderate',
                    'cancellation_risk'      => 'Medium (25% - 50%)',
                    'tier_grade'             => 'Tier C (Caution)',
                ];

            case 'bad_customer':
            case 'poor_customer':
            case 'bad':
            case 'poor':
            case 'high_risk':
            case 'fraud':
                return [
                    'estimated_success_rate' => 35.0,
                    'success_rate_range'     => '< 50%',
                    'delivery_reliability'   => 'Unreliable',
                    'cancellation_risk'      => 'High (> 50%)',
                    'tier_grade'             => 'Tier D (High Risk)',
                ];

            case 'new_customer':
            case 'new':
            default:
                return [
                    'estimated_success_rate' => null,
                    'success_rate_range'     => '—',
                    'delivery_reliability'   => 'New Customer',
                    'cancellation_risk'      => 'Unknown',
                    'tier_grade'             => 'New Buyer',
                ];
        }
    }

    /**
     * Standard response when customer has no delivery history with Pathao.
     */
    protected function formatNotFoundResponse(string $phone): array
    {
        return [
            'success'           => true,
            'phone'             => $phone,
            'courier'           => 'pathao',
            'total_orders'      => 0,
            'successful_orders' => 0,
            'cancelled_orders'  => 0,
            'success_rate'      => null,
            'customer_type'     => 'new_customer',
            'risk_level'        => 'unknown',
            'raw_available'     => true,
            'message'           => 'No previous delivery history found with Pathao.',
        ];
    }

    /**
     * Safe logging that never exposes secrets, tokens, or unmasked phone numbers.
     */
    protected function logCheck(string $maskedPhone, int $durationMs, bool $success, int $status, ?string $errorCode): void
    {
        Log::info('Pathao fraud check completed', [
            'phone'       => $maskedPhone,
            'duration_ms' => $durationMs,
            'status'      => $status,
            'success'     => $success,
            'error_code'  => $errorCode,
        ]);
    }
}
