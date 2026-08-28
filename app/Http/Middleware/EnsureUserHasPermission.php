<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
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

        // super_admin has all permissions
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Check via role model
        if ($user->role && $user->role->hasPermission($permission)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => "You do not have the '{$permission}' permission.",
        ], 403);
    }
}
