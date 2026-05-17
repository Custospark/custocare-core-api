<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Facility;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffFacilityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $staffId = $request->header('X-Staff-Id');

        if ($staffId) {
            $staff = Staff::find((int) $staffId);
            if (!$staff) {
                Log::warning('[StaffFacilityAccess] Staff not found', [
                    'staff_id_header' => $staffId,
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Staff record not found.',
                    'errors'  => ['staff' => ['No staff member matches the provided ID.']],
                    'data'    => null,
                ], 404);
            }

            $facilityId = $request->header('X-Active-Facility-Id')
                ?? $request->header('X-Facility-Id');

            if ($facilityId) {
                $facility = Facility::find((int) $facilityId);
                if (!$facility) {
                    Log::warning('[StaffFacilityAccess] Facility not found', [
                        'staff_id_header' => $staffId,
                        'resolved_staff_id' => $staff->id,
                        'facility_id_header' => $facilityId,
                        'ip' => $request->ip(),
                        'path' => $request->path(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Facility record not found.',
                        'errors'  => ['facility' => ['No facility matches the provided ID.']],
                        'data'    => null,
                    ], 404);
                }

                $hasAssignment = FacilityStaffRole::where('staff_id', $staff->id)
                    ->where('facility_id', $facility->id)
                    ->whereIn('assignment_status', ['active', 'on_leave'])
                    ->where('effective_from', '<=', now()->format('Y-m-d'))
                    ->where(function ($query) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', now()->format('Y-m-d'));
                    })
                    ->exists();

                if (!$hasAssignment) {
                    Log::warning('[StaffFacilityAccess] No valid assignment', [
                        'staff_id' => $staff->id,
                        'facility_id' => $facility->id,
                        'ip' => $request->ip(),
                        'path' => $request->path(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Staff is not assigned to this facility.',
                        'errors'  => [
                            'assignment' => ['No active or on-leave role assignment found for this staff at this facility.'],
                        ],
                        'data'    => null,
                    ], 403);
                }

                $request->attributes->set('resolved_facility', $facility);
            }

            $request->attributes->set('resolved_staff', $staff);
        }

        return $next($request);
    }
}
