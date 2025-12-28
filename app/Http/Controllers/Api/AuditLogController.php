<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\StoreAuditLogRequest;
use App\Http\Requests\AuditLog\UpdateAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\AuditLogCollection;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    /**
     * The audit log service instance.
     *
     * @var AuditLogServiceInterface
     */
    private $auditLogService;

    /**
     * Create a new controller instance.
     *
     * @param AuditLogServiceInterface $auditLogService
     * @return void
     */
    public function __construct(AuditLogServiceInterface $auditLogService)
    {
        $this->auditLogService = $auditLogService;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view audit logs')->only(['index', 'show', 'entity', 'patient', 'request']);
        // $this->middleware('permission:create audit logs')->only(['store']);
        // $this->middleware('permission:update audit logs')->only(['update']);
        // $this->middleware('permission:delete audit logs')->only(['destroy']);
        // $this->middleware('permission:export audit logs')->only(['export']);
        // $this->middleware('permission:view audit statistics')->only(['statistics']);
        // $this->middleware('permission:view hippa accounting')->only(['hippaAccounting']);
        // $this->middleware('permission:view phi access logs')->only(['phiAccess']);
        // $this->middleware('permission:manage legal hold')->only(['placeLegalHold', 'releaseLegalHold']);
        // $this->middleware('permission:run maintenance')->only(['archive', 'purge']);
    }

    /**
     * Display a listing of the audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewAny', \App\Models\AuditLog::class);

            // Get filters from request
            $filters = $request->only([
                'operation',
                'entity_type',
                'entity_id',
                'entity_identifier',
                'performed_by_type',
                'performed_by_id',
                'performed_by_identifier',
                'request_id',
                'compliance_reason',
                'legal_hold_flag',
                'facility_id',
                'department_id',
                'patient_id',
                'phi_accessed',
                'result',
                'error_code',
                'start_date',
                'end_date',
                'min_duration_ms',
                'max_duration_ms',
                'has_justification',
                'has_phi_fields',
            ]);

            // Validate facility access
            if (isset($filters['facility_id']) && !$request->user()->can('viewFacilityLogs', [\App\Models\AuditLog::class, $filters['facility_id']])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view logs for this facility.',
                ], 403);
            }

            // Validate patient access
            if (isset($filters['patient_id']) && !$request->user()->can('viewPatientLogs', [\App\Models\AuditLog::class, $filters['patient_id']])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view logs for this patient.',
                ], 403);
            }

            // Get pagination parameters
            $perPage = min($request->get('per_page', 50), 100); // Max 100 per page
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            // Get paginated audit logs
            $result = $this->auditLogService->getPaginatedAuditLogs(
                $filters,
                $perPage,
                $sortBy,
                $sortDirection
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }

            // Transform data using resources
            $logs = $result['data']['logs'];
            $pagination = $result['data']['pagination'];

            return (new AuditLogCollection($logs))
                ->additional([
                    'success' => true,
                    'message' => 'Audit logs retrieved successfully.',
                    'meta' => [
                        'pagination' => $pagination,
                        'filters' => $filters,
                        'sort' => [
                            'by' => $sortBy,
                            'direction' => $sortDirection,
                        ],
                    ],
                ])
                ->response();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs. Please try again later.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Store a newly created audit log in storage.
     *
     * @param StoreAuditLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreAuditLogRequest $request): JsonResponse
    {
        try {
            // The request already handles authorization via authorize() method
            
            // Create the audit log
            $result = $this->auditLogService->createAuditLog($request->validated());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? [],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Audit log created successfully.',
                'data' => $result['data'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create audit log. Please try again.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Display the specified audit log.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $auditLog = \App\Models\AuditLog::find($id);

            if (!$auditLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found.',
                ], 404);
            }

            // Authorize
            $this->authorize('view', $auditLog);

            $result = $this->auditLogService->getAuditLog($id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 404);
            }

            return (new AuditLogResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => 'Audit log retrieved successfully.',
                ])
                ->response();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this audit log.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit log', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit log.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Update the specified audit log in storage.
     *
     * @param UpdateAuditLogRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAuditLogRequest $request, int $id): JsonResponse
    {
        try {
            $auditLog = \App\Models\AuditLog::find($id);

            if (!$auditLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found.',
                ], 404);
            }

            // The request already handles authorization via authorize() method
            
            // Determine action based on request data
            if ($request->has('legal_hold_flag')) {
                if ($request->legal_hold_flag) {
                    // Place under legal hold
                    $result = $this->auditLogService->placeUnderLegalHold(
                        $id,
                        $request->validated()['metadata']['legal_hold_reason'] ?? 'Legal hold placed'
                    );
                } else {
                    // Release from legal hold
                    $result = $this->auditLogService->releaseFromLegalHold(
                        $id,
                        $request->validated()['metadata']['legal_hold_reason'] ?? 'Legal hold released'
                    );
                }

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                    ], 400);
                }

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'],
                ]);
            }

            // Default response for other updates (should not happen due to validation)
            return response()->json([
                'success' => false,
                'message' => 'Only legal hold status can be updated.',
            ], 400);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this audit log.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to update audit log', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update audit log.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Remove the specified audit log from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $auditLog = \App\Models\AuditLog::find($id);

            if (!$auditLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found.',
                ], 404);
            }

            // Authorize
            $this->authorize('delete', $auditLog);

            // Check if under legal hold
            if ($auditLog->legal_hold_flag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete audit log under legal hold.',
                ], 403);
            }

            // In production, we might soft delete or move to quarantine
            // For now, we'll prevent deletion to maintain audit trail
            return response()->json([
                'success' => false,
                'message' => 'Audit logs cannot be deleted to maintain compliance. Use archival instead.',
            ], 403);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this audit log.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete audit log', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete audit log.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific entity.
     *
     * @param Request $request
     * @param string $entityType
     * @param int|null $entityId
     * @return JsonResponse
     */
    public function entity(Request $request, string $entityType, ?int $entityId = null): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewAny', \App\Models\AuditLog::class);

            $filters = $request->only([
                'operation',
                'performed_by_type',
                'compliance_reason',
                'result',
                'start_date',
                'end_date',
            ]);

            $result = $this->auditLogService->getEntityAuditLogs(
                $entityType,
                $entityId,
                $filters
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'entity_type' => $result['data']['entity_type'],
                    'entity_id' => $result['data']['entity_id'],
                    'logs' => AuditLogResource::collection($result['data']['logs']),
                    'count' => $result['data']['count'],
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view entity audit logs.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve entity audit logs', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve entity audit logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function patient(Request $request, int $patientId): JsonResponse
    {
        try {
            // Authorize patient access
            if (!$request->user()->can('viewPatientLogs', [\App\Models\AuditLog::class, $patientId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view logs for this patient.',
                ], 403);
            }

            $filters = $request->only([
                'operation',
                'compliance_reason',
                'phi_accessed',
                'result',
                'start_date',
                'end_date',
            ]);

            $result = $this->auditLogService->getPatientAuditLogs($patientId, $filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'patient_id' => $result['data']['patient_id'],
                    'logs' => AuditLogResource::collection($result['data']['logs']),
                    'count' => $result['data']['count'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient audit logs', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient audit logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get HIPAA accounting of disclosures for a patient.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function hippaAccounting(Request $request, int $patientId): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewHippaAccounting', \App\Models\AuditLog::class);

            // Also check patient access
            if (!$request->user()->can('viewPatientLogs', [\App\Models\AuditLog::class, $patientId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view HIPAA accounting for this patient.',
                ], 403);
            }

            $filters = $request->only([
                'compliance_reason',
                'start_date',
                'end_date',
            ]);

            $result = $this->auditLogService->getHippaAccounting($patientId, $filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view HIPAA accounting.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve HIPAA accounting', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve HIPAA accounting.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get audit logs that accessed PHI.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function phiAccess(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewPhiAccessLogs', \App\Models\AuditLog::class);

            $filters = $request->only([
                'entity_type',
                'performed_by_type',
                'compliance_reason',
                'facility_id',
                'patient_id',
                'start_date',
                'end_date',
            ]);

            $result = $this->auditLogService->getPhiAccessAuditLogs($filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'logs' => AuditLogResource::collection($result['data']['logs']),
                    'count' => $result['data']['count'],
                    'phi_fields_summary' => $result['data']['phi_fields_summary'],
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view PHI access logs.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve PHI access logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PHI access logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get audit logs by request ID.
     *
     * @param string $requestId
     * @return JsonResponse
     */
    public function request(string $requestId): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewAny', \App\Models\AuditLog::class);

            $result = $this->auditLogService->getAuditLogsByRequestId($requestId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'request_id' => $result['data']['request_id'],
                    'logs' => AuditLogResource::collection($result['data']['logs']),
                    'timeline' => $result['data']['timeline'],
                    'statistics' => [
                        'total_operations' => $result['data']['total_operations'],
                        'successful_operations' => $result['data']['successful_operations'],
                        'failed_operations' => $result['data']['failed_operations'],
                        'total_duration_ms' => $result['data']['total_duration_ms'],
                    ],
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view audit logs.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit logs by request ID', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs by request ID.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get audit log statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('viewStatistics', \App\Models\AuditLog::class);

            $filters = $request->only([
                'operation',
                'entity_type',
                'performed_by_type',
                'compliance_reason',
                'facility_id',
                'patient_id',
                'phi_accessed',
                'result',
                'start_date',
                'end_date',
            ]);

            // Validate facility access
            if (isset($filters['facility_id']) && !$request->user()->can('viewFacilityLogs', [\App\Models\AuditLog::class, $filters['facility_id']])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view statistics for this facility.',
                ], 403);
            }

            $result = $this->auditLogService->getAuditLogStatistics($filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view audit log statistics.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit log statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit log statistics.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Export audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('export', \App\Models\AuditLog::class);

            $filters = $request->only([
                'operation',
                'entity_type',
                'performed_by_type',
                'compliance_reason',
                'facility_id',
                'patient_id',
                'phi_accessed',
                'result',
                'start_date',
                'end_date',
            ]);

            $format = $request->get('format', 'csv');

            // Validate format
            if (!in_array($format, ['csv', 'json', 'xml'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export format. Supported formats: csv, json, xml.',
                ], 400);
            }

            $result = $this->auditLogService->exportAuditLogs($filters, $format);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            // In production, you would return a download URL
            // For now, we'll return the data directly (not recommended for large exports)
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to export audit logs.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to export audit logs', [
                'format' => $request->get('format'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export audit logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Archive old audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function archive(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('runMaintenance', \App\Models\AuditLog::class);

            $batchSize = min($request->get('batch_size', 1000), 10000);

            $result = $this->auditLogService->processArchival($batchSize);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to run archival processes.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to archive audit logs', [
                'batch_size' => $request->get('batch_size'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive audit logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Purge expired audit logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function purge(Request $request): JsonResponse
    {
        try {
            // Authorize
            $this->authorize('runMaintenance', \App\Models\AuditLog::class);

            $batchSize = min($request->get('batch_size', 1000), 10000);

            $result = $this->auditLogService->processPurging($batchSize);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to run purging processes.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to purge audit logs', [
                'batch_size' => $request->get('batch_size'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to purge audit logs.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Place audit log under legal hold.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function placeLegalHold(Request $request, int $id): JsonResponse
    {
        try {
            $auditLog = \App\Models\AuditLog::find($id);

            if (!$auditLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found.',
                ], 404);
            }

            // Authorize
            $this->authorize('manageLegalHold', $auditLog);

            $request->validate([
                'reason' => 'required|string|min:10|max:500',
            ]);

            $result = $this->auditLogService->placeUnderLegalHold($id, $request->reason);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to place audit logs under legal hold.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to place audit log under legal hold', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to place audit log under legal hold.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Release audit log from legal hold.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function releaseLegalHold(Request $request, int $id): JsonResponse
    {
        try {
            $auditLog = \App\Models\AuditLog::find($id);

            if (!$auditLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit log not found.',
                ], 404);
            }

            // Authorize
            $this->authorize('manageLegalHold', $auditLog);

            $request->validate([
                'reason' => 'required|string|min:10|max:500',
            ]);

            $result = $this->auditLogService->releaseFromLegalHold($id, $request->reason);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to release audit logs from legal hold.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to release audit log from legal hold', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to release audit log from legal hold.',
                'errors' => config('app.debug') ? ['debug' => $e->getMessage()] : [],
            ], 500);
        }
    }
}