<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffPresence\UpdatePresenceRequest;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Services\StaffPresence\StaffPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffPresenceController extends Controller
{
    public function __construct(private StaffPresenceService $service) {}

    /**
     * Get current user's active presence in facility
     */
    public function myPresence(Request $request): JsonResponse
    {
        $staffId =Staff::where('user_id',Auth::id())->value('id');

        $facilityId = (int) $request->query('facility_id');

        $presence = $this->service->getActivePresence($staffId, $facilityId);

        return response()->json([
            'data' => $presence,
        ]);
    }

    /**
     * Set current user's presence status (in a facility)
     */
    public function setMyPresence(UpdatePresenceRequest $request): JsonResponse
    {
        $staffId =Staff::where('user_id',Auth::id())->value('id');
        $facilityId = (int) $request->input('facility_id');

        $updatedBy = $request->input('updated_by', 'staff');

        $presence = $this->service->setPresence(
            staffId: $staffId,
            facilityId: $facilityId,
            status: $request->input('status'),
            updatedBy: $updatedBy,
            updatedByUserId: Auth::id(),
            note: $request->input('note')
        );

        return response()->json([
            'message' => 'Work status updated successfully.',
            'data' => $presence,
        ]);
    }

    /**
     * List staff eligible for forwarding (facility scoped)
     */
    public function eligibleForForwarding(Request $request): JsonResponse
    {
        $facilityId = (int) $request->query('facility_id');

        $items = $this->service->listEligibleForForwarding($facilityId);

        return response()->json([
            'data' => $items,
        ]);
    }
}
