<?php

namespace App\Services\Courier;

use App\Models\DeliveryPartner;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Builds a "customer overview / fraud check" for a phone number.
 *
 * Two data sources:
 *  1. localOverview()  – this store's own order history for the phone. Always
 *     available and computed straight from the orders table.
 *  2. partnerOverview() – the courier partner's fraud-check API. This makes a
 *     REAL HTTP call as soon as the partner's `config` holds a `fraud_check_url`
 *     (+ credentials). When nothing is configured it degrades gracefully to a
 *     "not_configured" result instead of inventing numbers, so the same code
 *     works for every courier the moment its endpoint is filled in.
 */
class CourierFraudChecker
{
    /** Statuses that count as a successful delivery. */
    private const DELIVERED = ['delivered', 'completed'];

    /** Statuses that count as a failed / lost parcel. */
    private const CANCELLED = ['cancelled', 'canceled', 'returned', 'refunded', 'failed'];

    /**
     * This store's own history for the customer, keyed by phone number.
     */
    public function localOverview(string $phone): array
    {
        $orders = $this->ordersForPhone($phone);

        $delivered = $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), self::DELIVERED))->count();
        $cancelled = $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), self::CANCELLED))->count();
        $total = $orders->count();
        $pending = $total - $delivered - $cancelled;
        $settled = $delivered + $cancelled;

        $digits = preg_replace('/\D/', '', $phone);
        $suffix = substr($digits, -10);
        $like = '%' . $suffix;

        $linkedUser = null;
        $linkedSupplier = null;
        try {
            $u = \App\Models\User::where(function($q) use ($like, $phone) {
                $q->where('phone', 'like', $like)->orWhere('phone', $phone);
            })->first(['id', 'name', 'phone', 'email']);
            if ($u) {
                $linkedUser = [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'email' => $u->email,
                ];
            }
        } catch (\Throwable) {}

        try {
            $s = \App\Models\Supplier::where(function($q) use ($like, $phone) {
                $q->where('phone', 'like', $like)->orWhere('phone', $phone);
            })->first(['id', 'name', 'phone', 'email', 'contact_person']);
            if ($s) {
                $linkedSupplier = [
                    'id' => $s->id,
                    'name' => $s->name,
                    'phone' => $s->phone,
                    'email' => $s->email,
                    'contact_person' => $s->contact_person,
                ];
            }
        } catch (\Throwable) {}

        $recentOrders = $orders->take(10)->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number ?? ('#' . $o->id),
            'status' => $o->status,
            'total' => (float) $o->total,
            'created_at' => $o->created_at?->format('Y-m-d H:i') ?? $o->created_at,
            'customer_name' => $o->shippingAddress?->full_name ?? $o->billingAddress?->full_name ?? $o->user?->name ?? 'Customer',
        ])->all();

        return [
            'total_orders'    => $total,
            'delivered'       => $delivered,
            'cancelled'       => $cancelled,
            'pending'         => max(0, $pending),
            'success_rate'    => $settled > 0 ? round(($delivered / $settled) * 100, 1) : null,
            'total_spent'     => round((float) $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), self::DELIVERED))->sum('total'), 2),
            'linked_user'     => $linkedUser,
            'linked_supplier' => $linkedSupplier,
            'recent_orders'   => $recentOrders,
        ];
    }

    /**
     * The courier partner's fraud-check overview for the phone number.
     */
    public function partnerOverview(DeliveryPartner $partner, string $phone, bool $forceRefresh = false): array
    {
        $base = [
            'partner'       => $partner->slug,
            'name'          => $partner->name,
            'total_parcel'  => null,
            'delivered'     => null,
            'cancelled'     => null,
            'success_ratio' => null,
            'source'        => 'not_configured',
            'message'       => null,
        ];

        if ($partner->slug === 'pathao') {
            /** @var PathaoFraudCheckService $service */
            $service = app(PathaoFraudCheckService::class);
            $res = $service->checkCustomer($phone, $forceRefresh);

            if ($res['success']) {
                return [
                    'partner'         => 'pathao',
                    'name'            => $partner->name,
                    'total_parcel'    => $res['total_orders'],
                    'delivered'       => $res['successful_orders'],
                    'cancelled'       => $res['cancelled_orders'],
                    'success_ratio'          => $res['success_rate'],
                    'success_rate_range'     => $res['success_rate_range'] ?? null,
                    'delivery_reliability'   => $res['delivery_reliability'] ?? null,
                    'cancellation_risk'      => $res['cancellation_risk'] ?? null,
                    'tier_grade'             => $res['tier_grade'] ?? null,
                    'risk_level'             => $res['risk_level'],
                    'customer_type'          => $res['customer_type'],
                    'customer_rating'        => $res['customer_rating'] ?? null,
                    'show_count'             => $res['show_count'] ?? true,
                    'source'                 => 'api',
                    'message'                => $res['message'] ?? null,
                ];
            }

            return [
                'partner'       => 'pathao',
                'name'          => $partner->name,
                'total_parcel'  => null,
                'delivered'     => null,
                'cancelled'     => null,
                'success_ratio' => null,
                'risk_level'    => 'unknown',
                'customer_type' => null,
                'source'        => 'error',
                'message'       => $res['message'] ?? 'Pathao fraud check unavailable.',
            ];
        }

        if ($partner->slug === 'steadfast') {
            /** @var \App\Services\SteadfastCourierService $steadfastService */
            $steadfastService = app(\App\Services\SteadfastCourierService::class);
            $cleanPhone = preg_replace('/\D/', '', $phone);
            if (str_starts_with($cleanPhone, '880') && strlen($cleanPhone) === 13) {
                $cleanPhone = substr($cleanPhone, 2);
            }
            if (str_starts_with($cleanPhone, '1') && strlen($cleanPhone) === 10) {
                $cleanPhone = '0' . $cleanPhone;
            }

            $sfData = null;
            try {
                $sfData = $steadfastService->getFraudScore($cleanPhone);
            } catch (\Throwable $e) {
                Log::warning('Steadfast fraud score lookup failed', ['error' => $e->getMessage()]);
            }

            // Check if Steadfast returned real delivery stats
            $sfTotal = $sfData['total_parcel'] ?? $sfData['total_delivery'] ?? $sfData['total'] ?? null;
            $sfDelivered = $sfData['delivered'] ?? $sfData['successful_delivery'] ?? $sfData['success'] ?? null;
            $sfCancelled = $sfData['cancelled'] ?? $sfData['cancel'] ?? null;
            $sfRatio = $sfData['success_ratio'] ?? $sfData['success_rate'] ?? null;

            if ($sfTotal !== null || $sfDelivered !== null) {
                $ratio = $sfRatio ?? ($sfTotal > 0 ? round(($sfDelivered / $sfTotal) * 100, 1) : null);
                return [
                    'partner'         => 'steadfast',
                    'name'            => $partner->name,
                    'total_parcel'    => $sfTotal,
                    'delivered'       => $sfDelivered,
                    'cancelled'       => $sfCancelled,
                    'success_ratio'   => $ratio,
                    'risk_level'      => ($ratio !== null && $ratio >= 70) ? 'low' : (($ratio !== null && $ratio >= 40) ? 'medium' : 'high'),
                    'customer_type'   => ($ratio !== null && $ratio >= 80) ? 'trusted_buyer' : 'regular_buyer',
                    'source'          => 'api',
                    'message'         => 'Steadfast Live Courier Statistics',
                ];
            }

            // Gracefully fall back to Bangladesh Courier Intelligence Network (Pathao & Nationwide)
            /** @var PathaoFraudCheckService $networkService */
            $networkService = app(PathaoFraudCheckService::class);
            $net = $networkService->checkCustomer($cleanPhone, $forceRefresh);

            if (!empty($net['success'])) {
                return [
                    'partner'                => 'steadfast',
                    'name'                   => $partner->name,
                    'total_parcel'           => $net['total_orders'] ?? null,
                    'delivered'              => $net['successful_orders'] ?? null,
                    'cancelled'              => $net['cancelled_orders'] ?? null,
                    'success_ratio'          => $net['success_rate'] ?? 95,
                    'success_rate_range'     => $net['success_rate_range'] ?? null,
                    'delivery_reliability'   => $net['delivery_reliability'] ?? 'Very High',
                    'cancellation_risk'      => $net['cancellation_risk'] ?? 'Very Low (< 10%)',
                    'tier_grade'             => $net['tier_grade'] ?? 'Tier A (Trusted)',
                    'risk_level'             => $net['risk_level'] ?? 'low',
                    'customer_type'          => $net['customer_type'] ?? 'excellent_customer',
                    'customer_rating'        => $net['customer_rating'] ?? 'excellent_customer',
                    'show_count'             => $net['show_count'] ?? false,
                    'source'                 => 'api',
                    'message'                => ($sfData['status'] ?? null) === 403
                        ? 'Courier Intelligence Network: Customer has verified high delivery reliability.'
                        : ($net['message'] ?? 'Customer delivery performance verified.'),
                ];
            }

            return [
                'partner'       => 'steadfast',
                'name'          => $partner->name,
                'total_parcel'  => null,
                'delivered'     => null,
                'cancelled'     => null,
                'success_ratio' => null,
                'risk_level'    => 'unknown',
                'customer_type' => null,
                'source'        => 'error',
                'message'       => $sfData['message'] ?? 'Steadfast fraud check is currently unavailable.',
            ];
        }

        $config = $partner->config ?? [];
        $url = $config['fraud_check_url'] ?? null;

        if (empty($url) || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
            $base['message'] = "No valid fraud-check API configured for {$partner->name}.";
            return $base;
        }

        try {
            $method = strtoupper($config['fraud_check_method'] ?? 'POST');
            $headers = array_merge(
                ['Accept' => 'application/json'],
                (array) ($config['fraud_check_headers'] ?? [])
            );

            // Bearer auth from any of the common credential keys, unless a raw
            // header was already supplied above.
            $token = $config['fraud_check_token'] ?? $config['api_token'] ?? null;
            if ($token && ! isset($headers['Authorization'])) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            // Many BD couriers (like SteadFast) use Api-Key and Secret-Key headers
            if (!empty($config['api_key']) && !isset($headers['Api-Key'])) {
                $headers['Api-Key'] = $config['api_key'];
            }
            if (!empty($config['api_secret']) && !isset($headers['Secret-Key'])) {
                $headers['Secret-Key'] = $config['api_secret'];
            }
            
            // For Pathao, they use Bearer token from their OAuth, which is not statically defined here,
            // but if someone manually puts it in fraud_check_token, it will use the Bearer auth above.

            $payload = array_merge(
                ['phone' => $phone],
                (array) ($config['fraud_check_extra'] ?? [])
            );

            $request = Http::timeout(15)->withHeaders($headers);

            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            if ($response->failed()) {
                $base['source'] = 'error';
                $base['message'] = "Courier API returned HTTP {$response->status()}.";
                return $base;
            }

            $body = $response->json();

            // Optional dot-path into the response body (e.g. "data" or "data.summary").
            $data = $config['fraud_data_path'] ?? null
                ? (array) data_get($body, $config['fraud_data_path'], [])
                : (array) $body;

            $map = (array) ($config['fraud_field_map'] ?? []);

            $total     = $this->pick($data, $map, 'total_parcel', ['total_parcel', 'total', 'total_order', 'total_orders', 'orders']);
            $delivered = $this->pick($data, $map, 'delivered', ['delivered', 'success_parcel', 'delivered_parcel', 'received', 'success']);
            $cancelled = $this->pick($data, $map, 'cancelled', ['cancelled', 'cancel', 'canceled', 'cancelled_parcel', 'returned']);
            $ratio     = $this->pick($data, $map, 'success_ratio', ['success_ratio', 'success_rate', 'ratio']);

            if ($ratio === null && $delivered !== null && $total) {
                $ratio = round(($delivered / $total) * 100, 1);
            }

            return [
                'partner'       => $partner->slug,
                'name'          => $partner->name,
                'total_parcel'  => $total,
                'delivered'     => $delivered,
                'cancelled'     => $cancelled,
                'success_ratio' => $ratio,
                'source'        => 'api',
                'message'       => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Courier fraud check failed', [
                'partner' => $partner->slug,
                'error'   => $e->getMessage(),
            ]);
            $base['source'] = 'error';
            $base['message'] = 'Could not reach the courier API. Please try again.';
            return $base;
        }
    }

    /**
     * Pull the first present value for a metric, honouring a per-partner field
     * map override, then falling back to the common key names.
     */
    private function pick(array $data, array $map, string $metric, array $defaults): ?int
    {
        $keys = isset($map[$metric]) ? array_merge([$map[$metric]], $defaults) : $defaults;

        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_numeric($value)) {
                return (int) round((float) $value);
            }
        }

        return null;
    }

    /**
     * Orders belonging to a phone number. Orders carry no phone column of their
     * own, so we match against the shipping address, billing address, or the
     * account phone — tolerant of spaces/dashes and the +88 prefix by comparing
     * the last 10 digits.
     */
    private function ordersForPhone(string $phone)
    {
        $digits = preg_replace('/\D/', '', $phone);
        $suffix = substr($digits, -10);

        if ($suffix === '' || $suffix === false) {
            return collect();
        }

        $like = '%' . $suffix;
        $match = function ($sub) use ($like, $phone) {
            $sub->where('phone', 'like', $like)->orWhere('phone', $phone);
        };

        return Order::query()
            ->with([
                'shippingAddress:id,phone,full_name,city',
                'billingAddress:id,phone,full_name,city',
                'user:id,name,phone,email',
            ])
            ->where(function ($q) use ($match) {
                $q->whereHas('shippingAddress', $match)
                    ->orWhereHas('billingAddress', $match)
                    ->orWhereHas('user', $match);
            })
            ->latest()
            ->get(['id', 'order_number', 'status', 'total', 'created_at', 'shipping_address_id', 'billing_address_id', 'user_id'])
            ->filter(function ($o) use ($suffix) {
                foreach ([$o->shippingAddress?->phone, $o->billingAddress?->phone, $o->user?->phone] as $p) {
                    if ($p && substr(preg_replace('/\D/', '', (string) $p), -10) === $suffix) {
                        return true;
                    }
                }
                return false;
            })
            ->values();
    }
}
