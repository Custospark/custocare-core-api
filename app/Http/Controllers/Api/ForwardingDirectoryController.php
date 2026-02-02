<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StaffForwarding\ForwardingDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForwardingDirectoryController extends Controller
{
    public function __construct(private ForwardingDirectoryService $service) {}

    // GET /api/facilities/forwarding-directory?facility_id=12
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');

        // Later: compute allowedStaffIds based on visit phase + permissions
        $allowedStaffIds = null;

        $data = $this->service->list($facilityId, $allowedStaffIds);

        return response()->json(['data' => $data]);
    }
}
