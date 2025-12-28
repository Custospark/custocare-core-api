<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface for AuditLog Repository
 * Defines the contract for audit log data persistence operations.
 */
interface AuditLogRepositoryInterface
{
    /**
     * Create a new audit log.
     *
     * @param array $data
     * @return AuditLog
     */
    public function create(array $data): AuditLog;

    /**
     * Find an audit log by ID.
     *
     * @param int $id
     * @return AuditLog|null
     */
    public function findById(int $id): ?AuditLog;

    /**
     * Find an audit log by UUID.
     *
     * @param string $uuid
     * @return AuditLog|null
     */
    public function findByUuid(string $uuid): ?AuditLog;

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
    ): LengthAwarePaginator;

    /**
     * Get audit logs for a specific entity.
     *
     * @param string $entityType
     * @param int|null $entityId
     * @param array $filters
     * @return Collection
     */
    public function getForEntity(string $entityType, ?int $entityId = null, array $filters = []): Collection;

    /**
     * Get audit logs for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return Collection
     */
    public function getForPatient(int $patientId, array $filters = []): Collection;

    /**
     * Get audit logs for a specific compliance reason.
     *
     * @param string $complianceReason
     * @param array $filters
     * @return Collection
     */
    public function getForComplianceReason(string $complianceReason, array $filters = []): Collection;

    /**
     * Get audit logs for a specific time period.
     *
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon|null $endDate
     * @param array $filters
     * @return Collection
     */
    public function getForPeriod(\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate = null, array $filters = []): Collection;

    /**
     * Get audit logs that accessed PHI.
     *
     * @param array $filters
     * @return Collection
     */
    public function getPhiAccessLogs(array $filters = []): Collection;

    /**
     * Get audit logs by request ID for distributed tracing.
     *
     * @param string $requestId
     * @return Collection
     */
    public function getByRequestId(string $requestId): Collection;

    /**
     * Get audit logs for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getForFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get audit logs under legal hold.
     *
     * @param array $filters
     * @return Collection
     */
    public function getUnderLegalHold(array $filters = []): Collection;

    /**
     * Get audit logs eligible for archival.
     *
     * @param int $batchSize
     * @return Collection
     */
    public function getEligibleForArchival(int $batchSize = 1000): Collection;

    /**
     * Get audit logs eligible for purging.
     *
     * @param int $batchSize
     * @return Collection
     */
    public function getEligibleForPurging(int $batchSize = 1000): Collection;

    /**
     * Mark audit logs as archived.
     *
     * @param array $ids
     * @return int Number of logs archived
     */
    public function markAsArchived(array $ids): int;

    /**
     * Mark audit logs as purged.
     *
     * @param array $ids
     * @return int Number of logs purged
     */
    public function markAsPurged(array $ids): int;

    /**
     * Get statistics for audit logs.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Check if an audit log exists by UUID.
     *
     * @param string $uuid
     * @return bool
     */
    public function existsByUuid(string $uuid): bool;
}