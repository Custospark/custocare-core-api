<?php

namespace App\Http\Controllers\Api\Statistics;

use App\Http\Controllers\Controller;
use App\Services\Statistics\FacilityOwnerAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FacilityOwnerAnalyticsController extends Controller
{
    protected FacilityOwnerAnalyticsService $operationalService;

    public function __construct(FacilityOwnerAnalyticsService $operationalService)
    {
        $this->operationalService = $operationalService;
    }

    protected function resolveFacilityId(Request $request): int
    {
        return (int) $request->header('X-Facility-Id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $facilityId = $this->resolveFacilityId($request);
            if ($facilityId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing or invalid X-Facility-Id header.',
                    'errors' => ['facility_id' => ['Facility context is required for operational analytics.']],
                ], 422);
            }

            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
                'group_by'  => ['nullable', 'in:day,week,month'],
                'top'       => ['nullable', 'integer', 'min:1', 'max:25'],
            ]);

            Log::info('Operational decisions dashboard request.', [
                'facility_id' => $facilityId,
                'filters'     => $filters,
                'user_id'     => optional($request->user())->id,
            ]);

            $result = $this->operationalService->getDashboard($facilityId, $filters);

            return response()->json($result, $result['success'] ? 200 : 500);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Filter validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Operational decisions dashboard controller failed.', [
                'error_message' => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve operational dashboard data.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}