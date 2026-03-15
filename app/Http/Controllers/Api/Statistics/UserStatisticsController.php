<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserStatisticsRequest;
use App\Services\Statistics\UserStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * UserStatisticsController
 * 
 * API Controller for platform-wide user statistics.
 * Thin orchestration layer that delegates to UserStatisticsService.
 */
class UserStatisticsController extends Controller
{
    /**
     * @var UserStatisticsService
     */
    private UserStatisticsService $userStatisticsService;

    /**
     * UserStatisticsController constructor.
     *
     * @param UserStatisticsService $userStatisticsService
     */
    public function __construct(UserStatisticsService $userStatisticsService)
    {
        $this->userStatisticsService = $userStatisticsService;
    }

    /**
     * Get complete dashboard statistics
     *
     * @param UserStatisticsRequest $request
     * @return JsonResponse
     */
    public function dashboard(UserStatisticsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            
            $statistics = $this->userStatisticsService->getDashboardStatistics($filters);
            
            return response()->json([
                'success' => true,
                'message' => 'User dashboard statistics retrieved successfully',
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user dashboard statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user statistics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get key metrics
     *
     * @param UserStatisticsRequest $request
     * @return JsonResponse
     */
    public function keyMetrics(UserStatisticsRequest $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', '30_days');
            
            $metrics = $this->userStatisticsService->getKeyMetrics($dateRange);
            
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
     * Get verification funnel
     *
     * @return JsonResponse
     */
    public function verificationFunnel(): JsonResponse
    {
        try {
            $funnel = $this->userStatisticsService->getVerificationFunnel();
            
            return response()->json([
                'success' => true,
                'message' => 'Verification funnel retrieved successfully',
                'data' => $funnel,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve verification funnel', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve verification funnel',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get daily activity
     *
     * @param UserStatisticsRequest $request
     * @return JsonResponse
     */
    public function dailyActivity(UserStatisticsRequest $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', '30_days');
            
            $activity = $this->userStatisticsService->getDailyActivity($dateRange);
            
            return response()->json([
                'success' => true,
                'message' => 'Daily activity retrieved successfully',
                'data' => $activity,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve daily activity', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve daily activity',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get weekly trends
     *
     * @param UserStatisticsRequest $request
     * @return JsonResponse
     */
    public function weeklyTrends(UserStatisticsRequest $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', '12_weeks');
            
            $trends = $this->userStatisticsService->getWeeklyTrends($dateRange);
            
            return response()->json([
                'success' => true,
                'message' => 'Weekly trends retrieved successfully',
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve weekly trends', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weekly trends',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get monthly trends
     *
     * @return JsonResponse
     */
    public function monthlyTrends(): JsonResponse
    {
        try {
            $trends = $this->userStatisticsService->getMonthlyTrends();
            
            return response()->json([
                'success' => true,
                'message' => 'Monthly trends retrieved successfully',
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve monthly trends', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monthly trends',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get demographic distribution
     *
     * @return JsonResponse
     */
    public function demographicDistribution(): JsonResponse
    {
        try {
            $distribution = $this->userStatisticsService->getDemographicDistribution();
            
            return response()->json([
                'success' => true,
                'message' => 'Demographic distribution retrieved successfully',
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve demographic distribution', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve demographic distribution',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get MFA adoption statistics
     *
     * @return JsonResponse
     */
    public function mfaAdoption(): JsonResponse
    {
        try {
            $adoption = $this->userStatisticsService->getMfaAdoptionStats();
            
            return response()->json([
                'success' => true,
                'message' => 'MFA adoption statistics retrieved successfully',
                'data' => $adoption,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve MFA adoption statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve MFA adoption statistics',
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
            $distribution = $this->userStatisticsService->getGeographicDistribution();
            
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
     * Get platform breakdown
     *
     * @return JsonResponse
     */
    public function platformBreakdown(): JsonResponse
    {
        try {
            $breakdown = $this->userStatisticsService->getPlatformBreakdown();
            
            return response()->json([
                'success' => true,
                'message' => 'Platform breakdown retrieved successfully',
                'data' => $breakdown,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve platform breakdown', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve platform breakdown',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get user retention cohorts
     *
     * @return JsonResponse
     */
    public function userRetention(): JsonResponse
    {
        try {
            $retention = $this->userStatisticsService->getUserRetentionCohorts();
            
            return response()->json([
                'success' => true,
                'message' => 'User retention cohorts retrieved successfully',
                'data' => $retention,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user retention cohorts', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user retention cohorts',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get security metrics
     *
     * @return JsonResponse
     */
    public function securityMetrics(): JsonResponse
    {
        try {
            $metrics = $this->userStatisticsService->getSecurityMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'Security metrics retrieved successfully',
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve security metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve security metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Get staff performance metrics
     *
     * @return JsonResponse
     */
    public function staffPerformance(): JsonResponse
    {
        try {
            $performance = $this->userStatisticsService->getStaffPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'message' => 'Staff performance metrics retrieved successfully',
                'data' => $performance,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve staff performance metrics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff performance metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
            ], 500);
        }
    }

    /**
     * Export statistics as CSV
     *
     * @param UserStatisticsRequest $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
     */
    public function export(UserStatisticsRequest $request)
    {
        try {
            $filters = $request->validated();
            $statistics = $this->userStatisticsService->getDashboardStatistics($filters);
            
            $filename = 'user-statistics-' . now()->format('Y-m-d-His') . '.csv';
            
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
                
                // Verification funnel
                foreach ($statistics['verification_funnel']['funnel'] as $stage) {
                    fputcsv($file, [
                        'Verification Funnel',
                        $stage['stage'],
                        $stage['count'],
                        $statistics['timestamp']
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Failed to export statistics', [
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