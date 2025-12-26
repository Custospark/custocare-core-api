<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class ReturnUnauthenticatedJson
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Let the request proceed
            return $next($request);
        } catch (AuthenticationException $e) {
            // Only return JSON 401 for API routes
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Fallback for web routes
            return redirect()->guest(route('welcome')); // or login page
        }
    }
}
