<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            abort(403, 'Unauthorised.');
        }

        $candidates = array_filter(array_map('trim', explode('|', $permissions)));
        foreach ($candidates as $permissionKey) {
            if ($user->hasPermission($permissionKey)) {
                return $next($request);
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Forbidden: insufficient permissions (' . implode(' or ', $candidates) . ').',
            ], 403);
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
