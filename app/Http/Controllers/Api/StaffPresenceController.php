<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffPresence\UpdatePresenceRequest;
use App\Services\StaffPresence\StaffPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPresenceController extends Controller
{
    public function __construct(private StaffPresenceService $service) {}

    /**
     * Get current user's active presence in facility
     */
    public function myPresence(Request $request): JsonResponse
    {
        $user = $request->user();

        // You likely already have a way to resolve staff_id from auth user
        $staffId = $user->staff->id; // adjust to your structure

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
        $user = $request->user();
        $staffId = $user->staff->id; // adjust
        $facilityId = (int) $request->input('facility_id');

        $updatedBy = $request->input('updated_by', 'staff');

        $presence = $this->service->setPresence(
            staffId: $staffId,
            facilityId: $facilityId,
            status: $request->input('status'),
            updatedBy: $updatedBy,
            updatedByUserId: $user->id,
            note: $request->input('note')
        );

        return response()->json([
            'message' => 'Presence updated successfully.',
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
