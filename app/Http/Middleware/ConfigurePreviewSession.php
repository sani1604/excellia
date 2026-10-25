<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigurePreviewSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isSecure()) {
            config([
                'session.secure' => true,
                'session.same_site' => 'none',
                'session.partitioned' => true,
            ]);
        }

        return $next($request);
    }
}
