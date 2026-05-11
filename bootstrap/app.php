<?php

use App\Exceptions\MessageRecipientNotResolvedException;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureFacilitySubscriptionIsActive;
use App\Http\Middleware\ValidateActiveContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases (for route middleware)
        $middleware->alias([
            'facility.subscription.active' => EnsureFacilitySubscriptionIsActive::class,
            'admin.access'                 => EnsureAdminAccess::class,
            // 'validate.active.context'       => ValidateActiveContext::class, 
        ]);

        // Append to API middleware group if needed
        $middleware->api(append: [
            // 'validate.active.context', // Uncomment if you want it on all API routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (MessageRecipientNotResolvedException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [
                        'recipients' => [$e->getMessage()],
                        'recipient_channel' => [$e->channel],
                    ],
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication token is missing or invalid.',
                ], 401);
            }

            return redirect()->guest(route('login'));
        });
    })
    ->create();