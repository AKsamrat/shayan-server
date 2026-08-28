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

        return [
            'total_orders'  => $total,
            'delivered'     => $delivered,
            'cancelled'     => $cancelled,
            'pending'       => max(0, $pending),
            'success_rate'  => $settled > 0 ? round(($delivered / $settled) * 100, 1) : null,
            'total_spent'   => round((float) $orders->filter(fn ($o) => in_array(strtolower((string) $o->status), self::DELIVERED))->sum('total'), 2),
        ];
    }

    /**
     * The courier partner's fraud-check overview for the phone number.
     */
    public function partnerOverview(DeliveryPartner $partner, string $phone): array
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

        $config = $partner->config ?? [];
        $url = $config['fraud_check_url'] ?? null;

        if (empty($url)) {
            $base['message'] = "No fraud-check API configured for {$partner->name}. Add a \"fraud_check_url\" (and API key) in the partner settings to enable live checks.";
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
            $token = $config['fraud_check_token'] ?? $config['api_key'] ?? $config['api_token'] ?? null;
            if ($token && ! isset($headers['Authorization'])) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

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
                'shippingAddress:id,phone',
                'billingAddress:id,phone',
                'user:id,phone',
            ])
            ->where(function ($q) use ($match) {
                $q->whereHas('shippingAddress', $match)
                    ->orWhereHas('billingAddress', $match)
                    ->orWhereHas('user', $match);
            })
            ->get(['id', 'status', 'total', 'shipping_address_id', 'billing_address_id', 'user_id'])
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
