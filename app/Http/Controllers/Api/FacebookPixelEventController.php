<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookPixelEvent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacebookPixelEventController extends Controller
{
    use ApiResponse;

    /**
     * Store a new pixel event from the frontend.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => 'required|string|max:255',
            'event_data' => 'nullable|array',
            'source_url' => 'nullable|string',
            'fbp'        => 'nullable|string',
            'fbc'        => 'nullable|string',
        ]);

        $event = FacebookPixelEvent::create([
            'event_name' => $validated['event_name'],
            'event_data' => $validated['event_data'] ?? null,
            'source_url' => $validated['source_url'] ?? null,
            'user_id'    => auth('sanctum')->id(), // capture user if logged in
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fbp'        => $validated['fbp'] ?? null,
            'fbc'        => $validated['fbc'] ?? null,
        ]);

        return $this->success($event, 'Event stored', 201);
    }

    /**
     * Admin: Get all logged pixel events
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $events = FacebookPixelEvent::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'page', $page);

        return $this->success([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'per_page' => $events->perPage(),
        ]);
    }
}
