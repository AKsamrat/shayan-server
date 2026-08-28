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

        // super_admin always passes
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Check role enum
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // Check role model permissions if role_id is set
        if ($user->role_id && $user->role) {
            foreach ($roles as $role) {
                if ($user->role->slug === $role) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have the required role to access this resource.',
        ], 403);
    }
}
