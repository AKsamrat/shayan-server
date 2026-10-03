<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Courier\PathaoFraudCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PathaoFraudCheckController extends Controller
{
    protected PathaoFraudCheckService $fraudCheckService;

    public function __construct(PathaoFraudCheckService $fraudCheckService)
    {
        $this->fraudCheckService = $fraudCheckService;
    }

    /**
     * Check customer delivery history and risk assessment with Pathao.
     *
     * Request JSON:
     * {
     *   "phone": "01712345678",
     *   "refresh": false
     * }
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'         => 'required|string|max:30',
            'refresh'       => 'nullable|boolean',
            'force_refresh' => 'nullable|boolean',
        ]);

        $phone = trim($validated['phone']);
        $forceRefresh = (bool) ($validated['force_refresh'] ?? $validated['refresh'] ?? false);

        $result = $this->fraudCheckService->checkCustomer($phone, $forceRefresh);

        if (!$result['success']) {
            $status = match ($result['error'] ?? '') {
                'INVALID_PHONE'       => 422,
                'PATHAO_AUTH_FAILED'  => 401,
                'PATHAO_RATE_LIMITED' => 429,
                'PATHAO_UNAVAILABLE'  => 503,
                default               => 400,
            };

            return response()->json($result, $status);
        }

        return response()->json($result, 200);
    }
}
