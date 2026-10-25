<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if ($bearer !== null && $bearer !== '') {
            $tokenHash = hash('sha256', $bearer);
            $tokenUser = User::query()
                ->with('primaryRole')
                ->where('api_token_hash', $tokenHash)
                ->first();

            if (! $tokenUser) {
                return response()->json(['message' => 'Unauthenticated: invalid API token.'], 401);
            }

            Auth::setUser($tokenUser);
            $request->setUserResolver(fn () => $tokenUser);
            $user = $tokenUser;
        } else {
            $user = Auth::guard('web')->user();
        }

        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if (! $user->is_active || $user->trashed()) {
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Account is deactivated.'], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Contact the System Administrator.',
            ]);
        }

        return $next($request);
    }
}
