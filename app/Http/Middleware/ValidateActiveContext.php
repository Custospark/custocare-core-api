<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\FacilityStaffRole;
use Symfony\Component\HttpFoundation\Response;

class ValidateActiveContext
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the request includes valid active facility and role headers
     * and that the authenticated user has access to the specified role/facility.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $activeFacilityId = $request->header('X-Active-Facility-Id');
        $activeRoleCode = $request->header('X-Active-Role-Code');

        // Check if headers exist
        if (!$activeFacilityId || !$activeRoleCode) {
            return response()->json([
                'error' => 'Missing active context headers.'
            ], 400);
        }

        // Validate user has access to this facility/role
        $hasAccess = FacilityStaffRole::where('staff_id', $user->staff->id)
            ->where('facility_id', $activeFacilityId)
            ->where('role_code', $activeRoleCode)
            ->where('assignment_status', 'active')
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'error' => 'Invalid context: User does not have access to this role/facility.'
            ], 403);
        }

        // Merge validated context into request for easy access in controllers
        $request->merge([
            'active_facility_id' => (int) $activeFacilityId,
            'active_role_code' => $activeRoleCode,
        ]);

        return $next($request);
    }
}
