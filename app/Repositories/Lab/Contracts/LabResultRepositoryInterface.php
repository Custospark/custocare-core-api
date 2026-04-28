<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabResultRepositoryInterface
{
    /**
     * Find result by ID.
     *
     * @param int $id
     * @return LabResult|null
     */
    public function findById(int $id): ?LabResult;

    /**
     * Find result by UUID.
     *
     * @param string $uuid
     * @return LabResult|null
     */
    public function findByUuid(string $uuid): ?LabResult;

    /**
     * Get all results with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get results by lab request item.
     *
     * @param int $labRequestItemId
     * @return Collection
     */
    public function getByLabRequestItem(int $labRequestItemId): Collection;

    /**
     * Get results by template field.
     *
     * @param int $templateFieldId
     * @param array $filters
     * @return Collection
     */
    public function getByTemplateField(int $templateFieldId, array $filters = []): Collection;

    /**
     * Get results by flag.
     *
     * @param string $flag
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByFlag(string $flag, ?int $facilityId = null): Collection;

    /**
     * Get abnormal results.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getAbnormalResults(?int $facilityId = null): Collection;

    /**
     * Get critical results.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getCriticalResults(?int $facilityId = null): Collection;

    /**
     * Get pending results.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getPendingResults(?int $facilityId = null): Collection;

    /**
     * Get unverified results.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getUnverifiedResults(?int $facilityId = null): Collection;

    /**
     * Create a new result.
     *
     * @param array $data
     * @return LabResult
     */
    public function create(array $data): LabResult;

    /**
     * Bulk create results for a lab request item.
     *
     * @param int $labRequestItemId
     * @param array $results
     * @return Collection
     */
    public function bulkCreate(int $labRequestItemId, array $results): Collection;

    /**
     * Update an existing result.
     *
     * @param LabResult $result
     * @param array $data
     * @return bool
     */
    public function update(LabResult $result, array $data): bool;

    /**
     * Delete a result (soft delete).
     *
     * @param LabResult $result
     * @return bool
     */
    public function delete(LabResult $result): bool;

    /**
     * Restore a soft-deleted result.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Verify a result.
     *
     * @param LabResult $result
     * @param int $verifiedByStaffId
     * @return bool
     */
    public function verify(LabResult $result, int $verifiedByStaffId): bool;

    /**
     * Update result flag based on value.
     *
     * @param LabResult $result
     * @return bool
     */
    public function updateFlagFromValue(LabResult $result): bool;

    /**
     * Mark critical alert as sent.
     *
     * @param LabResult $result
     * @return bool
     */
    public function markCriticalAlertSent(LabResult $result): bool;

    /**
     * Get result with its relationships.
     *
     * @param int $id
     * @return LabResult|null
     */
    public function getWithRelations(int $id): ?LabResult;

    /**
     * Get results by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection;

    /**
     * Get results by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get result statistics.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getResultStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Get result trends for a test.
     *
     * @param int $labTestId
     * @param int $patientId
     * @param int $limit
     * @return Collection
     */
    public function getResultTrends(int $labTestId, int $patientId, int $limit = 10): Collection;

    /**
     * Get critical results requiring attention.
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getCriticalResultsRequiringAttention(int $facilityId): Collection;
}