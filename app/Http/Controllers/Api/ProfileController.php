<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use ApiResponse;

    // ==================== CUSTOMER PROFILE ====================

    /**
     * Get customer profile
     */
    public function getCustomerProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->success($user->load('role'), 'Customer profile retrieved');
    }

    /**
     * Update customer profile data
     */
    public function updateCustomerProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return $this->success($user->fresh()->load('role'), 'Customer profile updated successfully');
    }

    /**
     * Update customer profile image
     */
    public function updateCustomerImage(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return $this->success([
            'user' => $user->fresh(),
            'avatar_url' => Storage::url($path),
        ], 'Customer avatar updated successfully');
    }

    /**
     * Remove customer profile image
     */
    public function removeCustomerImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success($user->fresh(), 'Customer avatar removed successfully');
    }

    // ==================== ADMIN PROFILE ====================

    /**
     * Get admin/staff profile
     */
    public function getAdminProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->success($user->load(['role', 'role.permissions']), 'Admin profile retrieved');
    }

    /**
     * Update admin/staff profile data
     */
    public function updateAdminProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return $this->success($user->fresh()->load(['role', 'role.permissions']), 'Admin profile updated successfully');
    }

    /**
     * Update admin/staff profile image
     */
    public function updateAdminImage(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return $this->success([
            'user' => $user->fresh(),
            'avatar_url' => Storage::url($path),
        ], 'Admin avatar updated successfully');
    }

    /**
     * Remove admin/staff profile image
     */
    public function removeAdminImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->success($user->fresh(), 'Admin avatar removed successfully');
    }

    // ==================== ADMIN: MANAGE CUSTOMER PROFILES ====================

    /**
     * Get specific customer profile (for admin)
     */
    public function getCustomerById(int $id): JsonResponse
    {
        $customer = User::where('id', $id)->where('role', 'customer')->with('role')->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        return $this->success($customer, 'Customer retrieved successfully');
    }

    /**
     * Update customer profile data (by admin)
     */
    public function updateCustomerProfileByAdmin(Request $request, int $id): JsonResponse
    {
        $customer = User::where('id', $id)->where('role', 'customer')->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
        ]);

        $customer->update($validated);

        return $this->success($customer->fresh()->load('role'), 'Customer profile updated successfully');
    }

    /**
     * Update customer profile image (by admin)
     */
    public function updateCustomerImageByAdmin(Request $request, int $id): JsonResponse
    {
        $customer = User::where('id', $id)->where('role', 'customer')->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old avatar if exists
        if ($customer->avatar) {
            Storage::disk('public')->delete($customer->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $customer->update(['avatar' => $path]);

        return $this->success([
            'user' => $customer->fresh(),
            'avatar_url' => Storage::url($path),
        ], 'Customer avatar updated successfully');
    }

    /**
     * Remove customer profile image (by admin)
     */
    public function removeCustomerImageByAdmin(int $id): JsonResponse
    {
        $customer = User::where('id', $id)->where('role', 'customer')->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        if ($customer->avatar) {
            Storage::disk('public')->delete($customer->avatar);
            $customer->update(['avatar' => null]);
        }

        return $this->success($customer->fresh(), 'Customer avatar removed successfully');
    }

    // ==================== ADMIN: MANAGE STAFF PROFILES ====================

    /**
     * Get specific staff profile (for admin)
     */
    public function getStaffById(int $id): JsonResponse
    {
        $staff = User::where('id', $id)->whereIn('role', ['admin', 'staff'])->with(['role', 'role.permissions'])->first();

        if (!$staff) {
            return $this->error('Staff not found', 404);
        }

        return $this->success($staff, 'Staff retrieved successfully');
    }

    /**
     * Update staff profile data (by admin)
     */
    public function updateStaffProfileByAdmin(Request $request, int $id): JsonResponse
    {
        $staff = User::where('id', $id)->whereIn('role', ['admin', 'staff'])->first();

        if (!$staff) {
            return $this->error('Staff not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
            'role_id' => 'sometimes|exists:roles,id',
        ]);

        $staff->update($validated);

        return $this->success($staff->fresh()->load(['role', 'role.permissions']), 'Staff profile updated successfully');
    }

    /**
     * Update staff profile image (by admin)
     */
    public function updateStaffImageByAdmin(Request $request, int $id): JsonResponse
    {
        $staff = User::where('id', $id)->whereIn('role', ['admin', 'staff'])->first();

        if (!$staff) {
            return $this->error('Staff not found', 404);
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Delete old avatar if exists
        if ($staff->avatar) {
            Storage::disk('public')->delete($staff->avatar);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');
        $staff->update(['avatar' => $path]);

        return $this->success([
            'user' => $staff->fresh(),
            'avatar_url' => Storage::url($path),
        ], 'Staff avatar updated successfully');
    }

    /**
     * Remove staff profile image (by admin)
     */
    public function removeStaffImageByAdmin(int $id): JsonResponse
    {
        $staff = User::where('id', $id)->whereIn('role', ['admin', 'staff'])->first();

        if (!$staff) {
            return $this->error('Staff not found', 404);
        }

        if ($staff->avatar) {
            Storage::disk('public')->delete($staff->avatar);
            $staff->update(['avatar' => null]);
        }

        return $this->success($staff->fresh(), 'Staff avatar removed successfully');
    }
}
