<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nursing\NursingWardPatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NursingWardPatientController extends Controller
{
    public function __construct(
        protected NursingWardPatientService $nursingWardPatientService
    ) {}

    /**
     * Active visits with a ward/bed assignment (metadata.nursing_ward_bed) for the facility.
     *
     * Expects `X-Facility-Id` header.
     *
     * @queryParam ward_id optional Filter to a single ward
     * @queryParam limit optional Max rows (default 100, max 200)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $facilityId = (int) $request->header('X-Facility-Id', 0);

            if ($facilityId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'meta' => [],
                ], 422);
            }

            $validated = $request->validate([
                'ward_id' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            $wardId = isset($validated['ward_id']) ? (int) $validated['ward_id'] : null;
            $limit = (int) ($validated['limit'] ?? 100);

            $result = $this->nursingWardPatientService->listForFacility($facilityId, $wardId, $limit);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [],
                'meta' => $result['meta'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Nursing ward patients failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load ward patients.',
                'meta' => [],
            ], 500);
        }
    }
}
