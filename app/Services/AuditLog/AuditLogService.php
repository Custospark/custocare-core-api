<?php

namespace App\Services\AuditLog;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * AuditLog Service Implementation
 * Contains business logic for audit log operations.
 */
class AuditLogService implements AuditLogServiceInterface
{
    /**
     * The audit log repository instance.
     *
     * @var AuditLogRepositoryInterface
     */
    private $auditLogRepository;

    /**
     * Create a new service instance.
     *
     * @param AuditLogRepositoryInterface $auditLogRepository
     * @return void
     */
    public function __construct(AuditLogRepositoryInterface $auditLogRepository)
    {
        $this->auditLogRepository = $auditLogRepository;
    }

    /**
     * Create a new audit log entry.
     *
     * @param array $data
     * @return array
     */
    public function createAuditLog(array $data): array
    {
        try {
            // Validate data before creation
            $validationResult = $this->validateAuditLogData($data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationResult['errors'],
                    'data' => null,
                ];
            }

            // Ensure required fields are present
            $data = $this->enrichAuditLogData($data);

            // Create the audit log
            $auditLog = $this->auditLogRepository->create($data);

            return [
                'success' => true,
                'message' => 'Audit log created successfully',
                'data' => [
                    'id' => $auditLog->id,
                    'audit_uuid' => $auditLog->audit_uuid,
                    'created_at' => $auditLog->created_at->toISOString(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create audit log. Please try again.',
                'errors' => ['system' => 'Internal server error'],
                'data' => null,
            ];
        }
    }

    /**
     * Get an audit log by ID.
     *
     * @param int $id
     * @return array
     */
    public function getAuditLog(int $id): array
    {
        try {
            $auditLog = $this->auditLogRepository->findById($id);

            if (!$auditLog) {
                return [
                    'success' => false,
                    'message' => 'Audit log not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Audit log retrieved successfully',
                'data' => $auditLog,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit log by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve audit log',
                'data' => null,
            ];
        }
    }

    /**
     * Get an audit log by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getAuditLogByUuid(string $uuid): array
    {
        try {
            $auditLog = $this->auditLogRepository->findByUuid($uuid);

            if (!$auditLog) {
                return [
                    'success' => false,
                    'message' => 'Audit log not found',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Audit log retrieved successfully',
                'data' => $auditLog,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit log by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve audit log',
                'data' => null,
            ];
        }
    }

    /**
     * Get paginated audit logs with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDirection
     * @return array
     */
    public function getPaginatedAuditLogs(
        array $filters = [],
        int $perPage = 50,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): array {
        try {
            // Validate filters
            $this->validateFilters($filters);

            // Get paginated results
            $paginator = $this->auditLogRepository->paginateWithFilters(
                $filters,
                $perPage,
                $sortBy,
                $sortDirection
            );

            return [
                'success' => true,
                'message' => 'Audit logs retrieved successfully',
                'data' => [
                    'logs' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get paginated audit logs', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve audit logs',
                'data' => [
                    'logs' => [],
                    'pagination' => [
                        'total' => 0,
                        'per_page' => $perPage,
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => 0,
                        'to' => 0,
                    ],
                ],
            ];
        }
    }

    /**
     * Get audit logs for a specific entity.
     *
     * @param string $entityType
     * @param int|null $entityId
     * @param array $filters
     * @return array
     */
    public function getEntityAuditLogs(string $entityType, ?int $entityId = null, array $filters = []): array
    {
        try {
            // Validate entity type
            if (empty($entityType)) {
                return [
                    'success' => false,
                    'message' => 'Entity type is required',
                    'data' => null,
                ];
            }

            $logs = $this->auditLogRepository->getForEntity($entityType, $entityId, $filters);

            return [
                'success' => true,
                'message' => 'Entity audit logs retrieved successfully',
                'data' => [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get entity audit logs', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve entity audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getPatientAuditLogs(int $patientId, array $filters = []): array
    {
        try {
            $logs = $this->auditLogRepository->getForPatient($patientId, $filters);

            return [
                'success' => true,
                'message' => 'Patient audit logs retrieved successfully',
                'data' => [
                    'patient_id' => $patientId,
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient audit logs', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs for HIPAA accounting of disclosures.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getHippaAccounting(int $patientId, array $filters = []): array
    {
        try {
            // For HIPAA accounting, we need logs that accessed PHI
            $filters['phi_accessed'] = true;
            $logs = $this->auditLogRepository->getForPatient($patientId, $filters);

            // Calculate disclosure statistics
            $disclosuresByReason = [];
            $totalDisclosures = 0;

            foreach ($logs as $log) {
                $reason = $log->compliance_reason;
                if (!isset($disclosuresByReason[$reason])) {
                    $disclosuresByReason[$reason] = 0;
                }
                $disclosuresByReason[$reason]++;
                $totalDisclosures++;
            }

            return [
                'success' => true,
                'message' => 'HIPAA accounting retrieved successfully',
                'data' => [
                    'patient_id' => $patientId,
                    'logs' => $logs,
                    'total_disclosures' => $totalDisclosures,
                    'disclosures_by_reason' => $disclosuresByReason,
                    'period_covered' => $logs->isNotEmpty() 
                        ? [
                            'start' => $logs->last()->created_at->toDateString(),
                            'end' => $logs->first()->created_at->toDateString(),
                        ]
                        : null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get HIPAA accounting', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve HIPAA accounting',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs for a specific compliance reason.
     *
     * @param string $complianceReason
     * @param array $filters
     * @return array
     */
    public function getComplianceReasonAuditLogs(string $complianceReason, array $filters = []): array
    {
        try {
            $logs = $this->auditLogRepository->getForComplianceReason($complianceReason, $filters);

            return [
                'success' => true,
                'message' => 'Compliance reason audit logs retrieved successfully',
                'data' => [
                    'compliance_reason' => $complianceReason,
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get compliance reason audit logs', [
                'compliance_reason' => $complianceReason,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve compliance reason audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs for a specific time period.
     *
     * @param string $startDate
     * @param string|null $endDate
     * @param array $filters
     * @return array
     */
    public function getPeriodAuditLogs(string $startDate, ?string $endDate = null, array $filters = []): array
    {
        try {
            // Validate dates
            try {
                $start = Carbon::parse($startDate);
                $end = $endDate ? Carbon::parse($endDate) : null;
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Invalid date format',
                    'data' => null,
                ];
            }

            // Ensure end date is not before start date
            if ($end && $end->lt($start)) {
                return [
                    'success' => false,
                    'message' => 'End date must be after start date',
                    'data' => null,
                ];
            }

            $logs = $this->auditLogRepository->getForPeriod($start, $end, $filters);

            return [
                'success' => true,
                'message' => 'Period audit logs retrieved successfully',
                'data' => [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end ? $end->toDateString() : null,
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get period audit logs', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve period audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs that accessed PHI.
     *
     * @param array $filters
     * @return array
     */
    public function getPhiAccessAuditLogs(array $filters = []): array
    {
        try {
            $logs = $this->auditLogRepository->getPhiAccessLogs($filters);

            // Calculate statistics
            $phiFields = [];
            foreach ($logs as $log) {
                if ($log->phi_fields_accessed) {
                    foreach ($log->phi_fields_accessed as $field) {
                        if (!isset($phiFields[$field])) {
                            $phiFields[$field] = 0;
                        }
                        $phiFields[$field]++;
                    }
                }
            }

            arsort($phiFields);

            return [
                'success' => true,
                'message' => 'PHI access audit logs retrieved successfully',
                'data' => [
                    'logs' => $logs,
                    'count' => $logs->count(),
                    'phi_fields_summary' => $phiFields,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get PHI access audit logs', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve PHI access audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs by request ID for distributed tracing.
     *
     * @param string $requestId
     * @return array
     */
    public function getAuditLogsByRequestId(string $requestId): array
    {
        try {
            if (empty($requestId)) {
                return [
                    'success' => false,
                    'message' => 'Request ID is required',
                    'data' => null,
                ];
            }

            $logs = $this->auditLogRepository->getByRequestId($requestId);

            // Calculate request timeline
            $timeline = [];
            foreach ($logs as $log) {
                $timeline[] = [
                    'operation' => $log->operation,
                    'entity' => $log->entityName,
                    'performer' => $log->performerName,
                    'timestamp' => $log->created_at->toISOString(),
                    'duration_ms' => $log->operation_duration_ms,
                    'result' => $log->result,
                ];
            }

            return [
                'success' => true,
                'message' => 'Audit logs by request ID retrieved successfully',
                'data' => [
                    'request_id' => $requestId,
                    'logs' => $logs,
                    'timeline' => $timeline,
                    'total_operations' => $logs->count(),
                    'successful_operations' => $logs->where('result', 'success')->count(),
                    'failed_operations' => $logs->where('result', 'failure')->count(),
                    'total_duration_ms' => $logs->sum('operation_duration_ms'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs by request ID', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve audit logs by request ID',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getFacilityAuditLogs(int $facilityId, array $filters = []): array
    {
        try {
            $logs = $this->auditLogRepository->getForFacility($facilityId, $filters);

            return [
                'success' => true,
                'message' => 'Facility audit logs retrieved successfully',
                'data' => [
                    'facility_id' => $facilityId,
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get facility audit logs', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve facility audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit logs under legal hold.
     *
     * @param array $filters
     * @return array
     */
    public function getLegalHoldAuditLogs(array $filters = []): array
    {
        try {
            $logs = $this->auditLogRepository->getUnderLegalHold($filters);

            return [
                'success' => true,
                'message' => 'Legal hold audit logs retrieved successfully',
                'data' => [
                    'logs' => $logs,
                    'count' => $logs->count(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get legal hold audit logs', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve legal hold audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Get audit log statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getAuditLogStatistics(array $filters = []): array
    {
        try {
            $stats = $this->auditLogRepository->getStatistics($filters);

            return [
                'success' => true,
                'message' => 'Audit log statistics retrieved successfully',
                'data' => $stats,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit log statistics', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve audit log statistics',
                'data' => [
                    'total_logs' => 0,
                    'phi_access_logs' => 0,
                    'legal_hold_logs' => 0,
                    'successful_operations' => 0,
                    'failed_operations' => 0,
                    'average_duration_ms' => 0,
                    'by_operation' => [],
                    'by_compliance_reason' => [],
                ],
            ];
        }
    }

    /**
     * Process audit logs for archival.
     *
     * @param int $batchSize
     * @return array
     */
    public function processArchival(int $batchSize = 1000): array
    {
        try {
            DB::beginTransaction();

            // Get logs eligible for archival
            $eligibleLogs = $this->auditLogRepository->getEligibleForArchival($batchSize);

            if ($eligibleLogs->isEmpty()) {
                DB::rollBack();
                return [
                    'success' => true,
                    'message' => 'No audit logs eligible for archival',
                    'data' => ['archived_count' => 0],
                ];
            }

            // Mark logs as archived
            $ids = $eligibleLogs->pluck('id')->toArray();
            $archivedCount = $this->auditLogRepository->markAsArchived($ids);

            // In production, here you would:
            // 1. Export logs to cold storage (S3 Glacier, tape, etc.)
            // 2. Verify export succeeded
            // 3. Optionally delete from hot database

            DB::commit();

            Log::info('Audit logs archived', [
                'count' => $archivedCount,
                'batch_size' => $batchSize,
            ]);

            return [
                'success' => true,
                'message' => 'Audit logs archived successfully',
                'data' => [
                    'archived_count' => $archivedCount,
                    'total_eligible' => $eligibleLogs->count(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process audit log archival', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process audit log archival',
                'data' => null,
            ];
        }
    }

    /**
     * Process audit logs for purging.
     *
     * @param int $batchSize
     * @return array
     */
    public function processPurging(int $batchSize = 1000): array
    {
        try {
            DB::beginTransaction();

            // Get logs eligible for purging
            $eligibleLogs = $this->auditLogRepository->getEligibleForPurging($batchSize);

            if ($eligibleLogs->isEmpty()) {
                DB::rollBack();
                return [
                    'success' => true,
                    'message' => 'No audit logs eligible for purging',
                    'data' => ['purged_count' => 0],
                ];
            }

            // Mark logs as purged
            $ids = $eligibleLogs->pluck('id')->toArray();
            $purgedCount = $this->auditLogRepository->markAsPurged($ids);

            // In production, here you would:
            // 1. Delete from cold storage
            // 2. Verify deletion succeeded
            // 3. Log the purging operation itself

            DB::commit();

            Log::info('Audit logs purged', [
                'count' => $purgedCount,
                'batch_size' => $batchSize,
            ]);

            return [
                'success' => true,
                'message' => 'Audit logs purged successfully',
                'data' => [
                    'purged_count' => $purgedCount,
                    'total_eligible' => $eligibleLogs->count(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process audit log purging', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process audit log purging',
                'data' => null,
            ];
        }
    }

    /**
     * Place audit log under legal hold.
     *
     * @param int $id
     * @param string $reason
     * @return array
     */
    public function placeUnderLegalHold(int $id, string $reason): array
    {
        try {
            $auditLog = $this->auditLogRepository->findById($id);

            if (!$auditLog) {
                return [
                    'success' => false,
                    'message' => 'Audit log not found',
                    'data' => null,
                ];
            }

            if ($auditLog->legal_hold_flag) {
                return [
                    'success' => false,
                    'message' => 'Audit log is already under legal hold',
                    'data' => null,
                ];
            }

            // Update legal hold flag
            $auditLog->legal_hold_flag = true;
            
            // Add reason to metadata
            $metadata = $auditLog->metadata ?? [];
            $metadata['legal_hold_history'] = array_merge(
                $metadata['legal_hold_history'] ?? [],
                [
                    [
                        'action' => 'placed',
                        'reason' => $reason,
                        'timestamp' => now()->toISOString(),
                        'performed_by' => auth::id() ?? 'system',
                    ]
                ]
            );
            $auditLog->metadata = $metadata;
            
            // Save the audit log (this will throw exception due to immutability)
            // For legal hold, we need to bypass the immutability check
            DB::table('audit_logs')
                ->where('id', $id)
                ->update([
                    'legal_hold_flag' => true,
                    'metadata' => json_encode($metadata),
                ]);

            Log::info('Audit log placed under legal hold', [
                'id' => $id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Audit log placed under legal hold successfully',
                'data' => [
                    'id' => $id,
                    'legal_hold_flag' => true,
                    'reason' => $reason,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to place audit log under legal hold', [
                'id' => $id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to place audit log under legal hold',
                'data' => null,
            ];
        }
    }

    /**
     * Release audit log from legal hold.
     *
     * @param int $id
     * @param string $reason
     * @return array
     */
    public function releaseFromLegalHold(int $id, string $reason): array
    {
        try {
            $auditLog = $this->auditLogRepository->findById($id);

            if (!$auditLog) {
                return [
                    'success' => false,
                    'message' => 'Audit log not found',
                    'data' => null,
                ];
            }

            if (!$auditLog->legal_hold_flag) {
                return [
                    'success' => false,
                    'message' => 'Audit log is not under legal hold',
                    'data' => null,
                ];
            }

            // Update legal hold flag
            $auditLog->legal_hold_flag = false;
            
            // Add reason to metadata
            $metadata = $auditLog->metadata ?? [];
            $metadata['legal_hold_history'] = array_merge(
                $metadata['legal_hold_history'] ?? [],
                [
                    [
                        'action' => 'released',
                        'reason' => $reason,
                        'timestamp' => now()->toISOString(),
                        'performed_by' => auth::id() ?? 'system',
                    ]
                ]
            );
            $auditLog->metadata = $metadata;
            
            // Save the audit log (this will throw exception due to immutability)
            // For legal hold release, we need to bypass the immutability check
            DB::table('audit_logs')
                ->where('id', $id)
                ->update([
                    'legal_hold_flag' => false,
                    'metadata' => json_encode($metadata),
                ]);

            Log::info('Audit log released from legal hold', [
                'id' => $id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Audit log released from legal hold successfully',
                'data' => [
                    'id' => $id,
                    'legal_hold_flag' => false,
                    'reason' => $reason,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to release audit log from legal hold', [
                'id' => $id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to release audit log from legal hold',
                'data' => null,
            ];
        }
    }

    /**
     * Export audit logs for compliance reporting.
     *
     * @param array $filters
     * @param string $format
     * @return array
     */
    public function exportAuditLogs(array $filters = [], string $format = 'csv'): array
    {
        try {
            // Validate format
            $allowedFormats = ['csv', 'json', 'xml'];
            if (!in_array($format, $allowedFormats)) {
                return [
                    'success' => false,
                    'message' => 'Unsupported export format',
                    'data' => null,
                ];
            }

            // Get logs based on filters
            $paginator = $this->auditLogRepository->paginateWithFilters($filters, 1000);
            $logs = $paginator->items();

            // Prepare export data
            $exportData = $this->prepareExportData($logs, $format);

            // Generate filename
            $filename = 'audit_logs_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;

            // In production, you would:
            // 1. Store file in secure location (S3 with encryption)
            // 2. Generate pre-signed URL for download
            // 3. Log the export operation

            Log::info('Audit logs exported', [
                'format' => $format,
                'count' => count($logs),
                'filters' => $filters,
            ]);

            return [
                'success' => true,
                'message' => 'Audit logs exported successfully',
                'data' => [
                    'format' => $format,
                    'filename' => $filename,
                    'count' => count($logs),
                    'data' => $exportData,
                    'download_url' => null, // Would be generated in production
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to export audit logs', [
                'format' => $format,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to export audit logs',
                'data' => null,
            ];
        }
    }

    /**
     * Validate audit log data before creation.
     *
     * @param array $data
     * @return array
     */
    public function validateAuditLogData(array $data): array
    {
        $validator = Validator::make($data, [
            'operation' => 'required|in:create,read,update,delete,access,export,print,share,consent_change,authentication,authorization_failure',
            'entity_type' => 'required|string|max:100',
            'entity_id' => 'nullable|integer',
            'entity_identifier' => 'nullable|string|max:200',
            'previous_values' => 'nullable|array',
            'new_values' => 'nullable|array',
            'changed_fields' => 'nullable|array',
            'performed_by_type' => 'required|in:staff,patient,system,external_api,scheduled_job',
            'performed_by_id' => 'nullable|integer',
            'performed_by_identifier' => 'nullable|string|max:200',
            'performed_by_role' => 'nullable|string|max:100',
            'request_id' => 'required|string|max:100',
            'session_id' => 'nullable|string|max:100',
            'user_ip' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:512',
            'geolocation' => 'nullable|string|max:100',
            'compliance_reason' => 'required|in:treatment,payment,healthcare_operations,billing,audit,research,legal_request,patient_request,emergency_access,break_glass',
            'legal_hold_flag' => 'nullable|boolean',
            'justification' => 'nullable|string',
            'facility_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'patient_id' => 'nullable|integer',
            'phi_accessed' => 'nullable|boolean',
            'phi_fields_accessed' => 'nullable|array',
            'result' => 'required|in:success,failure,partial,denied',
            'failure_reason' => 'nullable|string',
            'error_code' => 'nullable|string|max:50',
            'operation_duration_ms' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        // Additional business logic validation
        $errors = [];

        // If PHI was accessed, phi_fields_accessed should be provided
        if (!empty($data['phi_accessed']) && empty($data['phi_fields_accessed'])) {
            $errors['phi_fields_accessed'] = ['PHI fields accessed must be specified when PHI is accessed.'];
        }

        // If operation failed, failure_reason should be provided
        if (in_array($data['result'] ?? null, ['failure', 'partial', 'denied']) && empty($data['failure_reason'])) {
            $errors['failure_reason'] = ['Failure reason must be specified when operation fails.'];
        }

        // Break glass access requires justification
        if (($data['compliance_reason'] ?? null) === 'break_glass' && empty($data['justification'])) {
            $errors['justification'] = ['Justification is required for break glass access.'];
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Enrich audit log data with additional information.
     *
     * @param array $data
     * @return array
     */
    private function enrichAuditLogData(array $data): array
    {
        // Set default values
        $data['legal_hold_flag'] = $data['legal_hold_flag'] ?? false;
        $data['phi_accessed'] = $data['phi_accessed'] ?? false;
        
        // Add audit UUID if not provided
        if (empty($data['audit_uuid'])) {
            $data['audit_uuid'] = \Illuminate\Support\Str::uuid()->toString();
        }
        
        // Add request context if not provided
        if (empty($data['user_ip']) && request()->ip()) {
            $data['user_ip'] = request()->ip();
        }
        
        if (empty($data['user_agent']) && request()->userAgent()) {
            $data['user_agent'] = request()->userAgent();
        }
        
        if (empty($data['session_id']) && session()->getId()) {
            $data['session_id'] = session()->getId();
        }
        
        // Add created_at if not provided
        if (empty($data['created_at'])) {
            $data['created_at'] = now();
        }
        
        return $data;
    }

    /**
     * Validate filters.
     *
     * @param array $filters
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateFilters(array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            switch ($key) {
                case 'operation':
                case 'compliance_reason':
                case 'result':
                    if (!is_string($value)) {
                        throw new \InvalidArgumentException("Filter '{$key}' must be a string.");
                    }
                    break;
                    
                case 'performed_by_id':
                case 'facility_id':
                case 'department_id':
                case 'patient_id':
                case 'entity_id':
                    if (!is_numeric($value)) {
                        throw new \InvalidArgumentException("Filter '{$key}' must be numeric.");
                    }
                    break;
                    
                case 'start_date':
                case 'end_date':
                    try {
                        Carbon::parse($value);
                    } catch (\Exception $e) {
                        throw new \InvalidArgumentException("Filter '{$key}' must be a valid date.");
                    }
                    break;
                    
                case 'phi_accessed':
                case 'legal_hold_flag':
                    if (!is_bool($value) && !in_array($value, ['true', 'false', '1', '0'])) {
                        throw new \InvalidArgumentException("Filter '{$key}' must be boolean.");
                    }
                    break;
            }
        }
    }

    /**
     * Prepare export data in specified format.
     *
     * @param array $logs
     * @param string $format
     * @return mixed
     */
    private function prepareExportData(array $logs, string $format)
    {
        $data = [];
        
        foreach ($logs as $log) {
            $data[] = [
                'audit_uuid' => $log->audit_uuid,
                'timestamp' => $log->created_at->toISOString(),
                'operation' => $log->operation,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'entity_identifier' => $log->entity_identifier,
                'performed_by_type' => $log->performed_by_type,
                'performed_by_id' => $log->performed_by_id,
                'performed_by_identifier' => $log->performed_by_identifier,
                'performed_by_role' => $log->performed_by_role,
                'compliance_reason' => $log->compliance_reason,
                'result' => $log->result,
                'phi_accessed' => $log->phi_accessed,
                'legal_hold' => $log->legal_hold_flag,
                'facility_id' => $log->facility_id,
                'department_id' => $log->department_id,
                'patient_id' => $log->patient_id,
                'operation_duration_ms' => $log->operation_duration_ms,
                'request_id' => $log->request_id,
                'user_ip' => $log->user_ip,
            ];
        }

        switch ($format) {
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
                
            case 'xml':
                $xml = new \SimpleXMLElement('<audit_logs></audit_logs>');
                foreach ($data as $item) {
                    $log = $xml->addChild('audit_log');
                    foreach ($item as $key => $value) {
                        $log->addChild($key, htmlspecialchars($value ?? ''));
                    }
                }
                return $xml->asXML();
                
            case 'csv':
            default:
                if (empty($data)) {
                    return '';
                }
                
                $csv = fopen('php://temp', 'r+');
                fputcsv($csv, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($csv, $row);
                }
                rewind($csv);
                $output = stream_get_contents($csv);
                fclose($csv);
                return $output;
        }
    }
}