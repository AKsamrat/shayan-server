<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use App\Models\Blog;
use App\Models\Slider;
use App\Models\Banner;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\OrderItem;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AdminAccount;
use App\Models\AdminAccountTransaction;
use App\Traits\ApiResponse;
use App\Models\Notification as NotificationModel;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\VendorShop;
use App\Models\DeliveryPartner;
use App\Models\DeliveryBooking;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\Brand;
use App\Models\FlashSale;
use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\ProductSpecification;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\RewardPoint;
use App\Services\Courier\CourierFraudChecker;
use App\Services\NotificationService;
use App\Services\RewardPointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
class AdminStaffController extends AdminController
{
    public function createStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $roleSlug = 'staff';
        $roleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;

        if ($roleId) {
            $roleModel = Role::find($roleId);
            if ($roleModel) {
                $validEnumRoles = ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'];
                $roleSlug = in_array($roleModel->slug, $validEnumRoles) ? $roleModel->slug : 'staff';
            }
        } elseif (!empty($validated['role'])) {
            $roleSlug = $validated['role'];
            $roleModel = Role::where('slug', $roleSlug)->first();
            if ($roleModel) {
                $roleId = $roleModel->id;
            }
        } else {
            $defaultStaffRole = Role::where('slug', 'staff')->first();
            if ($defaultStaffRole) {
                $roleId = $defaultStaffRole->id;
                $roleSlug = 'staff';
            }
        }

        if ($roleSlug === 'super_admin') {
            return $this->error('Cannot assign Super Admin role to staff.', 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $roleSlug,
            'role_id' => $roleId,
            'is_active' => $validated['is_active'] ?? true,
            'is_verified' => true,
        ]);

        if ($roleId) {
            $roleModel = Role::find($roleId);
            $user->role_name = $roleModel ? $roleModel->name : ucfirst($roleSlug);
            $user->role_details = $roleModel;
        } else {
            $user->role_name = ucfirst(str_replace('_', ' ', $roleSlug));
        }

        return $this->success($user->makeHidden(['password']), 'Staff member created successfully', 201);
    }

    public function updateStaff(Request $request, int $id): JsonResponse
    {
        $staff = User::where('role', '!=', 'super_admin')
            ->where(function ($q) {
                $q->whereIn('role', ['admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'])
                  ->orWhereNotNull('role_id');
            })->findOrFail($id);

        if ($staff->role === 'super_admin') {
            return $this->error('Super Admin cannot be modified via staff management.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'role' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (array_key_exists('role_id', $validated)) {
            $roleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;
            $validated['role_id'] = $roleId;
            if ($roleId) {
                $roleModel = Role::find($roleId);
                if ($roleModel) {
                    $validEnumRoles = ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'];
                    $validated['role'] = in_array($roleModel->slug, $validEnumRoles) ? $roleModel->slug : 'staff';
                }
            }
        } elseif (!empty($validated['role'])) {
            $roleModel = Role::where('slug', $validated['role'])->first();
            if ($roleModel) {
                $validated['role_id'] = $roleModel->id;
            }
        }

        $staff->update($validated);

        if ($staff->role_id) {
            $roleModel = Role::find($staff->role_id);
            $staff->role_name = $roleModel ? $roleModel->name : ucfirst($staff->role);
            $staff->role_details = $roleModel;
        } else {
            $staff->role_name = ucfirst(str_replace('_', ' ', $staff->role));
        }

        return $this->success($staff->fresh()->makeHidden(['password']), 'Staff member updated successfully');
    }

    public function deleteStaff(int $id): JsonResponse
    {
        $staff = User::where(function ($q) {
            $q->whereIn('role', ['admin', 'super_admin', 'manager', 'editor', 'staff', 'vendor_manager', 'support_agent', 'content_manager', 'marketing_manager'])
              ->orWhereNotNull('role_id');
        })->findOrFail($id);

        if (auth()->id() === $staff->id) {
            return $this->error('You cannot delete your own account', 400);
        }

        if ($staff->role === 'super_admin') {
            return $this->error('Super Admin account cannot be deleted', 403);
        }

        $staff->delete();

        return $this->success(null, 'Staff member deleted successfully');
    }

}

