<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffSpace\AssignStaffSpaceRequest;
use App\Services\StaffSpaceAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffSpaceAssignmentController extends Controller
{
    public function __construct(private StaffSpaceAssignmentService $service) {}

    // GET /api/staff/space?facility_id=12
    public function myCurrentSpace(Request $request): JsonResponse
    {
        $user = $request->user();
        $staffId = $user->staff->id; // adjust to your structure
        $facilityId = (int) $request->query('facility_id');

        $assignment = $this->service->getCurrentSpaceForStaff($staffId, $facilityId);

        return response()->json(['data' => $assignment]);
    }

    // POST /api/staff/space/assign
    public function assignMySpace(AssignStaffSpaceRequest $request): JsonResponse
    {
        $user = $request->user();
        $staffId = $user->staff->id; // adjust to your structure

        $facilityId = (int) $request->input('facility_id');
        $spaceId = (int) $request->input('space_id');

        $assignment = $this->service->assignStaffToSpace(
            staffId: $staffId,
            facilityId: $facilityId,
            spaceId: $spaceId,
            byUserId: $user->id,
            note: $request->input('note')
        );

        return response()->json([
            'message' => 'Space assigned successfully.',
            'data' => $assignment->load('space')
        ]);
    }

    // POST /api/staff/space/release
    public function releaseMySpace(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id']
        ]);

        $user = $request->user();
        $staffId = $user->staff->id; // adjust
        $facilityId = (int) $request->input('facility_id');

        $this->service->releaseStaffSpace($staffId, $facilityId, $user->id);

        return response()->json([
            'message' => 'Space released successfully.'
        ]);
    }

    // GET /api/facilities/spaces/occupancy?facility_id=12
    public function currentOccupancy(Request $request): JsonResponse
    {
        $facilityId = (int) $request->query('facility_id');

        $items = $this->service->listCurrentOccupancy($facilityId);

        return response()->json(['data' => $items]);
    }
}
