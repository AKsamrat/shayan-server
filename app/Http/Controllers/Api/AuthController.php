<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Cart;
use App\Models\Wallet;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->with('role')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated.', 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Build permissions array from role
        $roleModel = $user->role()->first();
        $permissions = $roleModel ? ($roleModel->permissions ?? []) : [];

        // Build user data: ensure `role` is always the string value (not the relationship object)
        $userData = $user->toArray();
        $userData['role'] = $roleModel ? $roleModel->slug : ($user->getOriginal('role') ?? 'customer');
        $userData['permissions'] = $permissions;

        return $this->success([
            'user' => $userData,
            'token' => $token,
            'refresh_token' => $token,
        ], 'Login successful');
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'is_verified' => false,
            'is_active' => true,
        ]);

        // Create wallet for user
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'BDT']);

        $token = $user->createToken('auth-token')->plainTextToken;

        $userData = $user->toArray();
        $userData['role'] = 'customer';
        $userData['permissions'] = [];

        return $this->success([
            'user' => $userData,
            'token' => $token,
        ], 'Registration successful', 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');
        $roleModel = $user->role;
        $permissions = $roleModel ? ($roleModel->permissions ?? []) : [];

        $userData = $user->toArray();
        $userData['role'] = $roleModel ? $roleModel->slug : ($user->getOriginal('role') ?? 'customer');
        $userData['permissions'] = $permissions;

        return $this->success($userData);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|string|max:500',
        ]);

        $user = $request->user();
        $user->update($validated);

        return $this->success($user->fresh(), 'Profile updated successfully');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return $this->success(null, 'Password changed successfully');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? $this->success(null, 'Reset link sent to your email')
            : $this->error('Unable to send reset link', 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? $this->success(null, 'Password reset successfully')
            : $this->error('Unable to reset password', 422);
    }
}
