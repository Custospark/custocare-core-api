<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ward\StoreWardRequest;
use App\Http\Requests\Ward\UpdateWardRequest;
use App\Models\Ward;
use App\Services\Ward\WardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WardController extends Controller
{
    public function __construct(private WardService $service) {}

    // GET /api/wards?facility_id=1&status=active&ward_type=medical&search=med
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'status' => ['nullable', 'in:active,inactive,temporarily_closed'],
            'ward_type' => ['nullable', 'in:medical,surgical,maternity,pediatric,icu,nicu,psychiatric,isolation,emergency_observation,general'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $facilityId = (int) $request->query('facility_id');

        $data = $this->service->list($facilityId, [
            'status' => $request->query('status'),
            'ward_type' => $request->query('ward_type'),
            'search' => $request->query('search'),
        ]);

        return response()->json(['data' => $data]);
    }

    // POST /api/wards
    public function store(StoreWardRequest $request): JsonResponse
    {
        $user = Auth::user();

        $ward = $this->service->create($request->validated(), $user->id);

        return response()->json([
            'message' => 'Ward created successfully.',
            'data' => $ward,
        ], 201);
    }

    // GET /api/wards/{ward}?facility_id=1
    public function show(Request $request, Ward $ward): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $this->service->ensureFacilityScope($ward, $facilityId);

        return response()->json(['data' => $ward]);
    }

    // PATCH /api/wards/{ward}?facility_id=1
    public function update(UpdateWardRequest $request, Ward $ward): JsonResponse
    {
        $facilityId = (int) $request->query('facility_id', $ward->facility_id);
        $this->service->ensureFacilityScope($ward, $facilityId);

        $ward = $this->service->update($ward, $request->validated(), Auth::id());

        return response()->json([
            'message' => 'Ward updated successfully.',
            'data' => $ward,
        ]);
    }

    // DELETE /api/wards/{ward}?facility_id=1
    public function destroy(Request $request, Ward $ward): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $this->service->ensureFacilityScope($ward, $facilityId);

        $this->service->delete($ward);

        return response()->json([
            'message' => 'Ward deleted successfully.',
        ]);
    }
}
