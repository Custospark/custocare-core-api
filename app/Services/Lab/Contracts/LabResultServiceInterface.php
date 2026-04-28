<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabResultServiceInterface
{
    /**
     * Get all results with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllResults(array $filters = [], int $perPage = 20): array;

    /**
     * Get result by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultByUuid(string $uuid): array;

    /**
     * Get result by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultById(int $id): array;

    /**
     * Create a new result.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createResult(array $data): array;

    /**
     * Update an existing result.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateResult(string $uuid, array $data): array;

    /**
     * Delete a result.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteResult(string $uuid): array;

    /**
     * Verify a result.
     *
     * @param string $uuid
     * @param int $verifiedByStaffId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function verifyResult(string $uuid, int $verifiedByStaffId): array;

    /**
     * Get results by lab request item.
     *
     * @param string $itemUuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultsByLabRequestItem(string $itemUuid): array;

    /**
     * Get results by template field.
     *
     * @param string $fieldUuid
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultsByTemplateField(string $fieldUuid, array $filters = []): array;

    /**
     * Get results by flag.
     *
     * @param string $flag
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultsByFlag(string $flag, ?int $facilityId = null): array;

    /**
     * Get abnormal results.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAbnormalResults(?int $facilityId = null): array;

    /**
     * Get critical results.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getCriticalResults(?int $facilityId = null): array;

    /**
     * Get critical results requiring attention.
     *
     * @param int $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getCriticalResultsRequiringAttention(int $facilityId): array;

    /**
     * Get unverified results.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getUnverifiedResults(?int $facilityId = null): array;

    /**
     * Get results by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultsByPatient(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Bulk create results for item.
     *
     * @param string $itemUuid
     * @param array $results
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function bulkCreateResults(string $itemUuid, array $results): array;

    /**
     * Get result with relationships.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultWithRelations(string $uuid): array;

    /**
     * Get result statistics.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Get result trends for a test.
     *
     * @param string $testUuid
     * @param int $patientId
     * @param int $limit
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getResultTrends(string $testUuid, int $patientId, int $limit = 10): array;

    /**
     * Mark critical alert as sent.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function markCriticalAlertSent(string $uuid): array;

    /**
     * Recalculate result flag.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function recalculateResultFlag(string $uuid): array;

    /**
     * Export results to CSV.
     *
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function exportResults(array $filters = []): array;
}