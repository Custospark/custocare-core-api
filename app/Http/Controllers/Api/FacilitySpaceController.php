<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilitySpace\StoreFacilitySpaceRequest;
use App\Http\Requests\FacilitySpace\UpdateFacilitySpaceRequest;
use App\Models\FacilitySpace;
use App\Models\StaffSpaceAssignment;
use App\Services\FacilitySpaceService\FacilitySpaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacilitySpaceController extends Controller
{
    public function __construct(private FacilitySpaceService $service) {}

    // GET /api/facilities/spaces?facility_id=12&active_only=1
    public function index(Request $request): JsonResponse
    {
        $facilityId = (int) $request->query('facility_id');
        $activeOnly = (bool) $request->query('active_only', false);

        $spaces = $this->service->listSpaces($facilityId, $activeOnly);

        return response()->json(['data' => $spaces]);
    }

    // POST /api/facilities/spaces
    public function store(StoreFacilitySpaceRequest $request): JsonResponse
    {
        $space = $this->service->createSpace([
            'facility_id' => (int) $request->input('facility_id'),
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'floor' => $request->input('floor'),
            'building' => $request->input('building'),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'message' => 'Space created successfully.',
            'data' => $space
        ], 201);
    }

    // PATCH /api/facilities/spaces/{space}
    public function update(UpdateFacilitySpaceRequest $request, FacilitySpace $space): JsonResponse
    {
        // facility scope guard (avoid editing a space from another facility context)
        // you can enforce via policy; here we do a light check if facility_id is provided
        if ($request->has('facility_id') && (int)$request->input('facility_id') !== (int)$space->facility_id) {
            return response()->json(['message' => 'Facility scope mismatch.'], 422);
        }

        $space = $this->service->updateSpace($space, $request->validated());

        return response()->json([
            'message' => 'Space updated successfully.',
            'data' => $space
        ]);
    }

    // GET /api/facilities/spaces/{space}
    public function show(FacilitySpace $space): JsonResponse
    {
        return response()->json(['data' => $space]);
    }

 public function destroy(FacilitySpace $space): JsonResponse
    {
        try {
            // Check if space has active assignments
            $activeAssignmentsCount = StaffSpaceAssignment::query()
                ->where('space_id', $space->id)
                ->whereNull('released_at')
                ->count();
            
            if ($activeAssignmentsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete space/room  with active staff assignments.',
                    'errors' => [
                        'space_id' => [
                            'This space has ' . $activeAssignmentsCount . ' active staff assignment(s). ' .
                            'Please release all staff from this space before deleting.'
                        ]
                    ]
                ], JsonResponse::HTTP_CONFLICT); // 409 Conflict
            }
            
            // Optionally: Check for any historical assignments before deletion
            $totalAssignmentsCount = StaffSpaceAssignment::query()
                ->where('space_id', $space->id)
                ->count();
            
            // Soft delete the space
            $space->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Space deleted successfully.',
                'meta' => [
                    'space_id' => $space->id,
                    'space_name' => $space->name,
                    'had_historical_assignments' => $totalAssignmentsCount > 0,
                    'historical_assignments_count' => $totalAssignmentsCount,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting space', [
                'space_id' => $space->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete space.',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while deleting the space.'
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

