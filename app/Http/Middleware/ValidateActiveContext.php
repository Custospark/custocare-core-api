<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\FacilityStaffRole;
use Symfony\Component\HttpFoundation\Response;

class ValidateActiveContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->staff) {
            return response()->json([
                'error' => 'Unauthenticated or invalid user context.'
            ], 401);
        }

        $activeFacilityId = (int) $request->header('X-Active-Facility-Id');
        $activeRoleCode   = trim($request->header('X-Active-Role-Code'));

        if (!$activeFacilityId || !$activeRoleCode) {
            return response()->json([
                'error' => 'Missing active context headers.'
            ], 400);
        }

        $hasAccess = FacilityStaffRole::where('staff_id', $user->staff->id)
            ->where('facility_id', $activeFacilityId)
            ->where('role_code', $activeRoleCode)
            ->where('assignment_status', 'active')
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'error' => 'Invalid context: access denied for this role and facility.'
            ], 403);
        }

        // Attach context safely (not as user input)
        $request->attributes->set('activeFacilityId', $activeFacilityId);
        $request->attributes->set('activeRoleCode', $activeRoleCode);

        return $next($request);
    }
}
