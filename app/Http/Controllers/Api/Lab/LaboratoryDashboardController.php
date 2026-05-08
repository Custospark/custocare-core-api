<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Services\Lab\LaboratoryDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LaboratoryDashboardController extends Controller
{
    public function __construct(
        protected LaboratoryDashboardService $laboratoryDashboardService
    ) {}

    /**
     * Facility laboratory intelligence dashboard payload.
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

            $result = $this->laboratoryDashboardService->getDashboard(
                $facilityId,
                $request->query('tz')
            );

            if (empty($result['success'])) {
                return response()->json($result, 500);
            }

            return response()->json($result, 200);
        } catch (\Throwable $e) {
            Log::error('Laboratory dashboard HTTP failed', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load laboratory dashboard.',
            ], 500);
        }
    }
}

