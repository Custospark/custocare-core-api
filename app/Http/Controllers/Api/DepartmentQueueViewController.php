<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepartmentQueueView\StoreDepartmentQueueViewRequest;
use App\Http\Requests\DepartmentQueueView\UpdateDepartmentQueueViewRequest;
use App\Http\Resources\DepartmentQueueViewResource;
use App\Models\DepartmentQueueView;
use App\Services\Contracts\DepartmentQueueViewServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepartmentQueueViewController extends Controller
{
    /**
     * Constructor with dependency injection
     */
    public function __construct(
        protected DepartmentQueueViewServiceInterface $service
    ) {
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view department queue views')->only(['index', 'show', 'facility', 'critical']);
        // $this->middleware('permission:create department queue views')->only(['store', 'batchUpdate']);
        // $this->middleware('permission:edit department queue views')->only(['update']);
        // $this->middleware('permission:delete department queue views')->only(['destroy']);
    }

    /**
     * Display a listing of department queue views.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['queue_type', 'capacity_status', 'current', 'date_from', 'date_to']);
            $facilityId = $request->input('facility_id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['The facility_id field is required.']]
                ], 422);
            }

            $queueViews = $this->service->getFacilityQueueViews($facilityId, $filters);
            
            return response()->json([
                'success' => true,
                'data' => DepartmentQueueViewResource::collection($queueViews),
                'meta' => [
                    'total' => $queueViews->count(),
                    'facility_id' => $facilityId,
                    'filters_applied' => $filters
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve department queue views', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department queue views',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created department queue view.
     */
    public function store(StoreDepartmentQueueViewRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validatedData();
            $result = $this->service->createQueueView($validatedData);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DepartmentQueueViewResource($result['data'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to create department queue view', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department queue view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified department queue view.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $queueView = $this->service->getQueueViewById($id);
            
            if (!$queueView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department queue view not found',
                    'code' => 404
                ], 404);
            }

            // Authorize view
            $this->authorize('view', $queueView);

            return response()->json([
                'success' => true,
                'data' => new DepartmentQueueViewResource($queueView->load(['facility', 'department']))
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve department queue view', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department queue view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified department queue view.
     */
    public function update(UpdateDepartmentQueueViewRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validatedData();
            $result = $this->service->updateQueueView($id, $validatedData);
            
            if (!$result['success']) {
                $statusCode = $result['code'] ?? 400;
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new DepartmentQueueViewResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to update department queue view', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department queue view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified department queue view.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Get queue view first to authorize
            $queueView = $this->service->getQueueViewById($id);
            
            if (!$queueView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department queue view not found',
                    'code' => 404
                ], 404);
            }

            // Authorize delete
            $this->authorize('delete', $queueView);

            $result = $this->service->deleteQueueView($id);
            
            if (!$result['success']) {
                $statusCode = $result['code'] ?? 400;
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to delete department queue view', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department queue view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get critical queues for a facility
     */
    public function critical(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->input('facility_id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['The facility_id field is required.']]
                ], 422);
            }

            // Authorize
            $this->authorize('viewAny', DepartmentQueueView::class);

            $criticalQueues = $this->service->getCriticalQueues($facilityId);
            
            return response()->json([
                'success' => true,
                'data' => DepartmentQueueViewResource::collection($criticalQueues),
                'meta' => [
                    'total_critical' => $criticalQueues->count(),
                    'facility_id' => $facilityId,
                    'timestamp' => now()->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve critical queues', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical queues',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get dashboard statistics for a facility
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->input('facility_id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required',
                    'errors' => ['facility_id' => ['The facility_id field is required.']]
                ], 422);
            }

            // Authorize
            $this->authorize('viewAny', DepartmentQueueView::class);

            $statistics = $this->service->getDashboardStatistics($facilityId);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'meta' => [
                    'facility_id' => $facilityId,
                    'generated_at' => now()->toIso8601String(),
                    'timeframe' => 'last 30 seconds'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve dashboard statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Batch update multiple queue views (for 30-second refresh)
     */
    public function batchUpdate(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('create', DepartmentQueueView::class);

            $queueData = $request->input('queue_views', []);
            
            if (empty($queueData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No queue data provided',
                    'errors' => ['queue_views' => ['The queue_views field is required and must be an array.']]
                ], 422);
            }

            $result = $this->service->batchUpdateQueueViews($queueData);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null,
                    'meta' => [
                        'valid_updates' => $result['valid_updates'] ?? 0,
                        'total_attempted' => count($queueData)
                    ]
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'updated_count' => $result['updated_count'],
                    'timestamp' => now()->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to batch update queue views', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch update queue views',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Analyze wait times for a department queue
     */
    public function analyzeWaitTimes(Request $request): JsonResponse
    {
        try {
            $departmentId = $request->input('department_id');
            $queueType = $request->input('queue_type');
            
            if (!$departmentId || !$queueType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department ID and Queue Type are required',
                    'errors' => [
                        'department_id' => $departmentId ? null : ['The department_id field is required.'],
                        'queue_type' => $queueType ? null : ['The queue_type field is required.']
                    ]
                ], 422);
            }

            // Authorize
            $this->authorize('viewAny', DepartmentQueueView::class);

            $result = $this->service->analyzeWaitTimes($departmentId, $queueType);
            
            if (!$result['success']) {
                $statusCode = $result['code'] ?? 400;
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'department_id' => $departmentId,
                    'queue_type' => $queueType,
                    'analyzed_at' => now()->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to analyze wait times', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze wait times',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generate predictions for a department queue
     */
    public function generatePredictions(Request $request): JsonResponse
    {
        try {
            $departmentId = $request->input('department_id');
            $queueType = $request->input('queue_type');
            
            if (!$departmentId || !$queueType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department ID and Queue Type are required',
                    'errors' => [
                        'department_id' => $departmentId ? null : ['The department_id field is required.'],
                        'queue_type' => $queueType ? null : ['The queue_type field is required.']
                    ]
                ], 422);
            }

            // Authorize
            $this->authorize('viewAny', DepartmentQueueView::class);

            $result = $this->service->generatePredictions($departmentId, $queueType);
            
            if (!$result['success']) {
                $statusCode = $result['code'] ?? 400;
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'department_id' => $departmentId,
                    'queue_type' => $queueType,
                    'prediction_timeframe' => 'next 60 minutes',
                    'generated_at' => now()->toIso8601String()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to generate predictions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate predictions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get queue view by department and type
     */
    public function byDepartmentAndType(Request $request): JsonResponse
    {
        try {
            $departmentId = $request->input('department_id');
            $queueType = $request->input('queue_type');
            
            if (!$departmentId || !$queueType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department ID and Queue Type are required',
                    'errors' => [
                        'department_id' => $departmentId ? null : ['The department_id field is required.'],
                        'queue_type' => $queueType ? null : ['The queue_type field is required.']
                    ]
                ], 422);
            }

            // Authorize
            $this->authorize('viewAny', DepartmentQueueView::class);

            $queueView = $this->service->getQueueViewByDepartmentAndType($departmentId, $queueType);
            
            if (!$queueView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department queue view not found for the specified department and queue type',
                    'code' => 404
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new DepartmentQueueViewResource($queueView->load(['facility', 'department']))
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve queue view by department and type', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue view',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}