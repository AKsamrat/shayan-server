<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PathaoCourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    protected $pathaoService;

    public function __construct(PathaoCourierService $pathaoService)
    {
        $this->pathaoService = $pathaoService;
    }

    public function getPathaoCities(): JsonResponse
    {
        try {
            $data = $this->pathaoService->getCities();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getPathaoZones(Request $request, $cityId): JsonResponse
    {
        try {
            $data = $this->pathaoService->getZones($cityId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getPathaoAreas(Request $request, $zoneId): JsonResponse
    {
        try {
            $data = $this->pathaoService->getAreas($zoneId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getPathaoStores(): JsonResponse
    {
        try {
            $data = $this->pathaoService->getStores();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPathaoStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:50',
            'contact_name' => 'required|string|min:3|max:50',
            'contact_number' => 'required|string|size:11',
            'secondary_contact' => 'nullable|string|size:11',
            'otp_number' => 'nullable|string',
            'address' => 'required|string|min:15|max:120',
            'city_id' => 'required|integer',
            'zone_id' => 'required|integer',
            'area_id' => 'required|integer',
        ]);

        try {
            $data = $this->pathaoService->createStore($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function calculatePathaoPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'item_type' => 'required|integer',
            'delivery_type' => 'required|integer',
            'item_weight' => 'required|numeric',
            'recipient_city' => 'required|integer',
            'recipient_zone' => 'required|integer',
        ]);

        try {
            $data = $this->pathaoService->calculatePrice($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createPathaoOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|integer',
            'merchant_order_id' => 'nullable|string',
            'recipient_name' => 'required|string|min:3|max:100',
            'recipient_phone' => 'required|string|size:11',
            'recipient_secondary_phone' => 'nullable|string|size:11',
            'recipient_address' => 'required|string|min:10|max:220',
            'recipient_city' => 'nullable|integer',
            'recipient_zone' => 'nullable|integer',
            'recipient_area' => 'nullable|integer',
            'delivery_type' => 'required|integer',
            'item_type' => 'required|integer',
            'special_instruction' => 'nullable|string',
            'item_quantity' => 'required|integer',
            'item_weight' => 'required|numeric',
            'item_description' => 'nullable|string',
            'amount_to_collect' => 'required|integer',
        ]);

        try {
            $data = $this->pathaoService->createOrder($validated);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getPathaoOrderInfo(Request $request, $consignmentId): JsonResponse
    {
        try {
            $data = $this->pathaoService->getOrderInfo($consignmentId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
