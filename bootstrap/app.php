<?php

use App\Exceptions\DomainRuleException;
use App\Exceptions\DriveIntegrationException;
use App\Http\Middleware\ConfigurePreviewSession;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequirePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->prependToGroup('web', ConfigurePreviewSession::class);
        $middleware->alias([
            'active.user' => EnsureActiveUser::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainRuleException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], $e->httpStatus);
            }

            if ($e->httpStatus === 403) {
                abort(403, $e->getMessage());
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->render(function (DriveIntegrationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => 'GOOGLE_DRIVE_UNAVAILABLE',
                ], 502);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
