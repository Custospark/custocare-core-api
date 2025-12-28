<?php

namespace App\Repositories\AuditLog;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuditLog Repository Implementation
 * Handles all database operations for audit logs.
 */
class AuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * Create a new audit log.
     *
     * @param array $data
     * @return AuditLog
     */
    public function create(array $data): AuditLog
    {
        try {
            return AuditLog::create($data);
        } catch (\Exception $e) {
            // Log the error but don't expose database details
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'data' => $this->sanitizeLogData($data)
            ]);
            
            // For audit logs, we should still try to persist even on failure
            // This ensures we don't lose audit trail due to database issues
            throw new \RuntimeException('Failed to create audit log. Please try again.');
        }
    }

    /**
     * Find an audit log by ID.
     *
     * @param int $id
     * @return AuditLog|null
     */
    public function findById(int $id): ?AuditLog
    {
        try {
            return AuditLog::find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find audit log by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Find an audit log by UUID.
     *
     * @param string $uuid
     * @return AuditLog|null
     */
    public function findByUuid(string $uuid): ?AuditLog
    {
        try {
            return AuditLog::where('audit_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find audit log by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get paginated audit logs with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDirection
     * @return LengthAwarePaginator
     */
    public function paginateWithFilters(
        array $filters = [],
        int $perPage = 50,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        try {
            $query = AuditLog::query();
            
            // Apply filters
            $this->applyFilters($query, $filters);
            
            // Apply sorting
            $query->orderBy($sortBy, $sortDirection);
            
            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate audit logs', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator on error
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get audit logs for a specific entity.
     *
     * @param string $entityType
     * @param int|null $entityId
     * @param array $filters
     * @return Collection
     */
    public function getForEntity(string $entityType, ?int $entityId = null, array $filters = []): Collection
    {
        try {
            $query = AuditLog::forEntity($entityType, $entityId);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs for entity', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return Collection
     */
    public function getForPatient(int $patientId, array $filters = []): Collection
    {
        try {
            $query = AuditLog::forPatient($patientId);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs for patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs for a specific compliance reason.
     *
     * @param string $complianceReason
     * @param array $filters
     * @return Collection
     */
    public function getForComplianceReason(string $complianceReason, array $filters = []): Collection
    {
        try {
            $query = AuditLog::forComplianceReason($complianceReason);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs for compliance reason', [
                'compliance_reason' => $complianceReason,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs for a specific time period.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon|null $endDate
     * @param array $filters
     * @return Collection
     */
    public function getForPeriod(\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate = null, array $filters = []): Collection
    {
        try {
            $query = AuditLog::forPeriod($startDate, $endDate);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs for period', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs that accessed PHI.
     *
     * @param array $filters
     * @return Collection
     */
    public function getPhiAccessLogs(array $filters = []): Collection
    {
        try {
            $query = AuditLog::accessedPhi();
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get PHI access logs', [
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs by request ID for distributed tracing.
     *
     * @param string $requestId
     * @return Collection
     */
    public function getByRequestId(string $requestId): Collection
    {
        try {
            return AuditLog::where('request_id', $requestId)
                ->orderBy('created_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs by request ID', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getForFacility(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = AuditLog::forFacility($facilityId);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs for facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs under legal hold.
     *
     * @param array $filters
     * @return Collection
     */
    public function getUnderLegalHold(array $filters = []): Collection
    {
        try {
            $query = AuditLog::where('legal_hold_flag', true);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs under legal hold', [
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs eligible for archival.
     *
     * @param int $batchSize
     * @return Collection
     */
    public function getEligibleForArchival(int $batchSize = 1000): Collection
    {
        try {
            return AuditLog::where('archived_at', null)
                ->where('legal_hold_flag', false)
                ->where('created_at', '<=', now()->subDays(90))
                ->orderBy('created_at', 'asc')
                ->limit($batchSize)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs eligible for archival', [
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Get audit logs eligible for purging.
     *
     * @param int $batchSize
     * @return Collection
     */
    public function getEligibleForPurging(int $batchSize = 1000): Collection
    {
        try {
            return AuditLog::where('archived_at', '!=', null)
                ->where('purged_at', null)
                ->where('legal_hold_flag', false)
                ->where('created_at', '<=', now()->subYears(7))
                ->orderBy('created_at', 'asc')
                ->limit($batchSize)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get audit logs eligible for purging', [
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Mark audit logs as archived.
     *
     * @param array $ids
     * @return int Number of logs archived
     */
    public function markAsArchived(array $ids): int
    {
        try {
            return AuditLog::whereIn('id', $ids)
                ->where('archived_at', null)
                ->update(['archived_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Failed to mark audit logs as archived', [
                'ids' => $ids,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Mark audit logs as purged.
     *
     * @param array $ids
     * @return int Number of logs purged
     */
    public function markAsPurged(array $ids): int
    {
        try {
            return AuditLog::whereIn('id', $ids)
                ->where('purged_at', null)
                ->update(['purged_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Failed to mark audit logs as purged', [
                'ids' => $ids,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Get statistics for audit logs.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        try {
            $query = AuditLog::query();
            $this->applyFilters($query, $filters);
            
            return [
                'total_logs' => (int) $query->count(),
                'phi_access_logs' => (int) $query->clone()->where('phi_accessed', true)->count(),
                'legal_hold_logs' => (int) $query->clone()->where('legal_hold_flag', true)->count(),
                'successful_operations' => (int) $query->clone()->where('result', 'success')->count(),
                'failed_operations' => (int) $query->clone()->where('result', 'failure')->count(),
                'average_duration_ms' => (float) $query->clone()->avg('operation_duration_ms'),
                'by_operation' => $query->clone()
                    ->select('operation', DB::raw('count(*) as count'))
                    ->groupBy('operation')
                    ->pluck('count', 'operation')
                    ->toArray(),
                'by_compliance_reason' => $query->clone()
                    ->select('compliance_reason', DB::raw('count(*) as count'))
                    ->groupBy('compliance_reason')
                    ->pluck('count', 'compliance_reason')
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit log statistics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_logs' => 0,
                'phi_access_logs' => 0,
                'legal_hold_logs' => 0,
                'successful_operations' => 0,
                'failed_operations' => 0,
                'average_duration_ms' => 0,
                'by_operation' => [],
                'by_compliance_reason' => [],
            ];
        }
    }

    /**
     * Check if an audit log exists by UUID.
     *
     * @param string $uuid
     * @return bool
     */
    public function existsByUuid(string $uuid): bool
    {
        try {
            return AuditLog::where('audit_uuid', $uuid)->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if audit log exists by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Apply filters to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            switch ($key) {
                case 'operation':
                case 'performed_by_type':
                case 'compliance_reason':
                case 'result':
                case 'error_code':
                    $query->where($key, $value);
                    break;
                    
                case 'performed_by_id':
                case 'facility_id':
                case 'department_id':
                case 'patient_id':
                case 'entity_id':
                    $query->where($key, (int) $value);
                    break;
                    
                case 'entity_type':
                    $query->where('entity_type', 'like', "%{$value}%");
                    break;
                    
                case 'entity_identifier':
                case 'performed_by_identifier':
                    $query->where($key, 'like', "%{$value}%");
                    break;
                    
                case 'phi_accessed':
                case 'legal_hold_flag':
                    $query->where($key, (bool) $value);
                    break;
                    
                case 'start_date':
                    $query->where('created_at', '>=', $value);
                    break;
                    
                case 'end_date':
                    $query->where('created_at', '<=', $value);
                    break;
                    
                case 'request_id':
                    $query->where('request_id', $value);
                    break;
                    
                case 'has_phi_fields':
                    if ($value) {
                        $query->whereNotNull('phi_fields_accessed');
                    }
                    break;
                    
                case 'has_justification':
                    if ($value) {
                        $query->whereNotNull('justification');
                    } else {
                        $query->whereNull('justification');
                    }
                    break;
                    
                case 'min_duration_ms':
                    $query->where('operation_duration_ms', '>=', (int) $value);
                    break;
                    
                case 'max_duration_ms':
                    $query->where('operation_duration_ms', '<=', (int) $value);
                    break;
            }
        }
    }

    /**
     * Sanitize log data to remove sensitive information.
     *
     * @param array $data
     * @return array
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveFields = [
            'previous_values',
            'new_values',
            'phi_fields_accessed',
            'justification',
            'metadata'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}