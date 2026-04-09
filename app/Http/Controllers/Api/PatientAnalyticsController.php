<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Patient\Analytics\PatientAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PatientAnalyticsController extends Controller
{
    protected PatientAnalyticsService $dashboardService;

    public function __construct(PatientAnalyticsService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    protected function resolveFacilityId(Request $request): int
    {
        return (int) $request->header('X-Facility-Id');
    }

    public function overview(Request $request): JsonResponse
    {
        try {
            $facilityId = $this->resolveFacilityId($request);
            if ($facilityId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['Facility context is required for dashboard analytics.']],
                ], 422);
            }

            $validated = $request->validate([
                'period' => ['nullable', 'in:today,week,month,custom'],
                'date_from' => ['nullable', 'date', 'required_if:period,custom'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'required_if:period,custom'],
            ]);

            $period = $validated['period'] ?? 'week';
            $dateFrom = null;
            $dateTo = null;

            if ($period === 'custom') {
                $dateFrom = $validated['date_from'];
                $dateTo = $validated['date_to'];
            }

            $data = $this->dashboardService->getDashboard($facilityId, $period, $dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Dashboard overview failed.', [
                'facility_id' => $facilityId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}