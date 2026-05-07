<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nursing\NursingDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NursingDashboardController extends Controller
{
    public function __construct(
        protected NursingDashboardService $nursingDashboardService
    ) {}

    /**
     * Facility nursing intelligence dashboard (single payload).
     *
     * Expects `X-Facility-Id` header to match `{facilityId}` for tenancy checks.
     */
    public function show(Request $request, int $facilityId): JsonResponse
    {
        try {
            $headerFacilityId = (int) $request->header('X-Facility-Id', 0);

            if ($headerFacilityId <= 0 || $headerFacilityId !== $facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid facility access.',
                ], 403);
            }

            $result = $this->nursingDashboardService->getDashboard(
                $facilityId,
                $request->query('tz')
            );

            if (empty($result['success'])) {
                return response()->json($result, 500);
            }

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error('Nursing dashboard HTTP failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load nursing dashboard.',
            ], 500);
        }
    }
}
