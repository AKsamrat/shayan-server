<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)->get();
        return $this->success($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:billing,shipping',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'address_line_1' => 'required|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'is_default' => 'sometimes|boolean',
        ]);

        $validated['user_id'] = $request->user()->id;

        if (isset($validated['is_default']) && $validated['is_default']) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address = Address::create($validated);

        return $this->success($address, 'Address created successfully', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:billing,shipping',
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email',
            'address_line_1' => 'sometimes|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:100',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return $this->success($address->fresh(), 'Address updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();

        return $this->success(null, 'Address deleted successfully');
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);

        Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->success($address->fresh(), 'Default address updated');
    }
}
