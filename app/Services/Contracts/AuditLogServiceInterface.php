<?php

namespace App\Services\Contracts;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface for AuditLog Service
 * Defines business logic operations for audit logs.
 */
interface AuditLogServiceInterface
{
    /**
     * Create a new audit log entry.
     *
     * @param array $data
     * @return array
     */
    public function createAuditLog(array $data): array;

    /**
     * Get an audit log by ID.
     *
     * @param int $id
     * @return array
     */
    public function getAuditLog(int $id): array;

    /**
     * Get an audit log by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getAuditLogByUuid(string $uuid): array;

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
    ): array;

    /**
     * Get audit logs for a specific entity.
     *
     * @param string $entityType
     * @param int|null $entityId
     * @param array $filters
     * @return array
     */
    public function getEntityAuditLogs(string $entityType, ?int $entityId = null, array $filters = []): array;

    /**
     * Get audit logs for a specific patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getPatientAuditLogs(int $patientId, array $filters = []): array;

    /**
     * Get audit logs for HIPAA accounting of disclosures.
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getHippaAccounting(int $patientId, array $filters = []): array;

    /**
     * Get audit logs for a specific compliance reason.
     *
     * @param string $complianceReason
     * @param array $filters
     * @return array
     */
    public function getComplianceReasonAuditLogs(string $complianceReason, array $filters = []): array;

    /**
     * Get audit logs for a specific time period.
     *
     * @param string $startDate
     * @param string|null $endDate
     * @param array $filters
     * @return array
     */
    public function getPeriodAuditLogs(string $startDate, ?string $endDate = null, array $filters = []): array;

    /**
     * Get audit logs that accessed PHI.
     *
     * @param array $filters
     * @return array
     */
    public function getPhiAccessAuditLogs(array $filters = []): array;

    /**
     * Get audit logs by request ID for distributed tracing.
     *
     * @param string $requestId
     * @return array
     */
    public function getAuditLogsByRequestId(string $requestId): array;

    /**
     * Get audit logs for a specific facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getFacilityAuditLogs(int $facilityId, array $filters = []): array;

    /**
     * Get audit logs under legal hold.
     *
     * @param array $filters
     * @return array
     */
    public function getLegalHoldAuditLogs(array $filters = []): array;

    /**
     * Get audit log statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getAuditLogStatistics(array $filters = []): array;

    /**
     * Process audit logs for archival.
     *
     * @param int $batchSize
     * @return array
     */
    public function processArchival(int $batchSize = 1000): array;

    /**
     * Process audit logs for purging.
     *
     * @param int $batchSize
     * @return array
     */
    public function processPurging(int $batchSize = 1000): array;

    /**
     * Place audit log under legal hold.
     *
     * @param int $id
     * @param string $reason
     * @return array
     */
    public function placeUnderLegalHold(int $id, string $reason): array;

    /**
     * Release audit log from legal hold.
     *
     * @param int $id
     * @param string $reason
     * @return array
     */
    public function releaseFromLegalHold(int $id, string $reason): array;

    /**
     * Export audit logs for compliance reporting.
     *
     * @param array $filters
     * @param string $format
     * @return array
     */
    public function exportAuditLogs(array $filters = [], string $format = 'csv'): array;

    /**
     * Validate audit log data before creation.
     *
     * @param array $data
     * @return array
     */
    public function validateAuditLogData(array $data): array;
}