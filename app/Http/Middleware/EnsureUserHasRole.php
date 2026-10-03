<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        // Flatten roles if they were passed as a single comma-separated string
        $flatRoles = [];
        foreach ($roles as $r) {
            $flatRoles = array_merge($flatRoles, explode(',', $r));
        }
        $flatRoles = array_map('trim', $flatRoles);

        // Get the string value of the role (bypassing relationship just in case)
        $roleString = $user->getAttributes()['role'] ?? '';

        // super_admin always passes
        if ($roleString === 'super_admin') {
            return $next($request);
        }

        // Check role enum
        if (in_array($roleString, $flatRoles)) {
            return $next($request);
        }

        // Check role model permissions if role_id is set
        if ($user->role_id) {
            $roleModel = $user->role()->first();
            if ($roleModel) {
                foreach ($flatRoles as $role) {
                    if ($roleModel->slug === $role) {
                        return $next($request);
                    }
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have the required role to access this resource.',
            'debug' => [
                'user_role' => $roleString,
                'required_roles' => $flatRoles,
                'user_id' => $user->id,
            ]
        ], 403);
    }
}
