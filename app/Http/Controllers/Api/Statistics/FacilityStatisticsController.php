<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facility\FacilityStatisticsRequest;
use App\Services\Statistics\FacilityStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * FacilityStatisticsController
 * 
 * API Controller for platform-wide facility statistics.
 * Thin orchestration layer that delegates to FacilityStatisticsService.
 */
class FacilityStatisticsController extends Controller
{
    /**
     * @var FacilityStatisticsService
     */
    private FacilityStatisticsService $facilityStatisticsService;

    /**
     * FacilityStatisticsController constructor.
     *
     * @param FacilityStatisticsService $facilityStatisticsService
     */
    public function __construct(FacilityStatisticsService $facilityStatisticsService)
    {
        $this->facilityStatisticsService = $facilityStatisticsService;
    }

    /**
     * Get complete dashboard statistics
     *
     * @param FacilityStatisticsRequest $request
     * @return JsonResponse
     */
    public function dashboard(FacilityStatisticsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            
            $statistics = $this->facilityStatisticsService->getDashboardStatistics($filters);
            
            return response()->json([
                'success' => true,
                'message' => 'Facility dashboard statistics retrieved successfully',
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility dashboard statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get key metrics
     *
     * @return JsonResponse
     */
    public function keyMetrics(): JsonResponse
    {
        try {
            $metrics = $this->facilityStatisticsService->getKeyMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'Key metrics retrieved successfully',
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve key metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve key metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get facility type distribution
     *
     * @return JsonResponse
     */
    public function facilityTypeDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getFacilityTypeDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Facility type distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility type distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility type distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get facility tier distribution
     *
     * @return JsonResponse
     */
    public function facilityTierDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getFacilityTierDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Facility tier distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility tier distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility tier distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get nature distribution
     *
     * @return JsonResponse
     */
    public function natureDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getNatureDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Nature of facility distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve nature distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve nature distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get operational status distribution
     *
     * @return JsonResponse
     */
    public function operationalStatusDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getOperationalStatusDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Operational status distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve operational status distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve operational status distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get geographic distribution
     *
     * @return JsonResponse
     */
    public function geographicDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getGeographicDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Geographic distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve geographic distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve geographic distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get capacity metrics
     *
     * @return JsonResponse
     */
    public function capacityMetrics(): JsonResponse
    {
        try {
            $metrics = $this->facilityStatisticsService->getCapacityMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'Capacity metrics retrieved successfully',
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve capacity metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve capacity metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get service availability
     *
     * @return JsonResponse
     */
    public function serviceAvailability(): JsonResponse
    {
        try {
            $availability = $this->facilityStatisticsService->getServiceAvailability();
            
            return response()->json([
                'success' => true,
                'message' => 'Service availability retrieved successfully',
                'data' => $availability,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve service availability', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service availability',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get specialty services
     *
     * @return JsonResponse
     */
    public function specialtyServices(): JsonResponse
    {
        try {
            $services = $this->facilityStatisticsService->getSpecialtyServices();
            
            return response()->json([
                'success' => true,
                'message' => 'Specialty services retrieved successfully',
                'data' => $services,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve specialty services', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve specialty services',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get emergency capabilities
     *
     * @return JsonResponse
     */
    public function emergencyCapabilities(): JsonResponse
    {
        try {
            $capabilities = $this->facilityStatisticsService->getEmergencyCapabilities();
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency capabilities retrieved successfully',
                'data' => $capabilities,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve emergency capabilities', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergency capabilities',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get accreditation statistics
     *
     * @return JsonResponse
     */
    public function accreditationStats(): JsonResponse
    {
        try {
            $stats = $this->facilityStatisticsService->getAccreditationStats();
            
            return response()->json([
                'success' => true,
                'message' => 'Accreditation statistics retrieved successfully',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve accreditation statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accreditation statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get license expiry metrics
     *
     * @return JsonResponse
     */
    public function licenseExpiryMetrics(): JsonResponse
    {
        try {
            $metrics = $this->facilityStatisticsService->getLicenseExpiryMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'License expiry metrics retrieved successfully',
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve license expiry metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve license expiry metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get performance metrics
     *
     * @return JsonResponse
     */
    public function performanceMetrics(): JsonResponse
    {
        try {
            $metrics = $this->facilityStatisticsService->getPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'Performance metrics retrieved successfully',
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve performance metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve performance metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get data residency distribution
     *
     * @return JsonResponse
     */
    public function dataResidencyDistribution(): JsonResponse
    {
        try {
            $distribution = $this->facilityStatisticsService->getDataResidencyDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Data residency distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve data residency distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve data residency distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get facility growth trends
     *
     * @return JsonResponse
     */
    public function facilityGrowthTrends(): JsonResponse
    {
        try {
            $trends = $this->facilityStatisticsService->getFacilityGrowthTrends();
            
            return response()->json([
                'success' => true,
                'message' => 'Facility growth trends retrieved successfully',
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility growth trends', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility growth trends',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Export statistics as CSV
     *
     * @param FacilityStatisticsRequest $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function export(FacilityStatisticsRequest $request)
    {
        try {
            $statistics = $this->facilityStatisticsService->getDashboardStatistics();
            
            $filename = 'facility-statistics-' . now()->format('Y-m-d-His') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];
            
            $callback = function() use ($statistics) {
                $file = fopen('php://output', 'w');
                
                // Add headers
                fputcsv($file, ['Section', 'Metric', 'Value', 'Timestamp']);
                
                // Key metrics
                foreach ($statistics['key_metrics'] as $metric) {
                    fputcsv($file, [
                        'Key Metrics',
                        $metric['label'],
                        $metric['value'],
                        $statistics['timestamp']
                    ]);
                }
                
                // Facility type distribution
                foreach ($statistics['facility_type_distribution'] as $type) {
                    fputcsv($file, [
                        'Facility Type',
                        $type['type_label'],
                        $type['count'],
                        $statistics['timestamp']
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Failed to export facility statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }
}