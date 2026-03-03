<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureAdminAccess
 *
 * Restricts access to routes that require platform-level
 * administrator privileges via Spatie roles.
 *
 * Usage:
 *   Route::middleware(['auth:sanctum', 'admin.access'])
 */
class EnsureAdminAccess
{
    /** Roles permitted to access admin-level routes. */
    private const ALLOWED_ROLES = ['super_admin'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data'    => null,
            ], 401);
        }

        /**
         * Authorization Architecture Overview:
         *
         * - Platform-level authority → Enforced via Users table (Spatie roles).
         * - Facility-level authority → Managed through the Staff model.
         * - Subscription enforcement → Determined by resolved Facility context.
         *
         * This separation ensures clear responsibility boundaries in a multi-tenant SaaS architecture.
         */

        if (! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Administrator privileges required.',
                'errors'  => [
                    'authorization' => [
                        'You do not have permission to perform this action.',
                    ],
                ],
                'data' => null,
            ], 403);
        }

        // Attach admin user for downstream use (platform-level admin, not facility staff)
        $request->attributes->set('admin_user', $user);

        return $next($request);
    }
}