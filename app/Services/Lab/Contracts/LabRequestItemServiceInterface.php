<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabRequestItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabRequestItemServiceInterface
{
    /**
     * Get all items with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllItems(array $filters = [], int $perPage = 20): array;

    /**
     * Get item by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemByUuid(string $uuid): array;

    /**
     * Get item by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemById(int $id): array;

    /**
     * Create a new item.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createItem(array $data): array;

    /**
     * Update an existing item.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateItem(string $uuid, array $data): array;

    /**
     * Delete an item.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteItem(string $uuid): array;

    /**
     * Update item status.
     *
     * @param string $uuid
     * @param string $status
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateItemStatus(string $uuid, string $status): array;

    /**
     * Mark sample as collected.
     *
     * @param string $uuid
     * @param int $collectedByStaffId
     * @param string|null $sampleIdentifier
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function markSampleCollected(string $uuid, int $collectedByStaffId, ?string $sampleIdentifier = null): array;

    /**
     * Mark item as in progress.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function markItemInProgress(string $uuid): array;

    /**
     * Mark item as completed.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function markItemCompleted(string $uuid): array;

    /**
     * Mark item as verified.
     *
     * @param string $uuid
     * @param int $verifiedByStaffId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function markItemVerified(string $uuid, int $verifiedByStaffId): array;

    /**
     * Cancel an item.
     *
     * @param string $uuid
     * @param string $reason
     * @param int|null $cancelledByStaffId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function cancelItem(string $uuid, string $reason, ?int $cancelledByStaffId = null): array;

    /**
     * Get items by lab request.
     *
     * @param string $requestUuid
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemsByLabRequest(string $requestUuid, array $filters = []): array;

    /**
     * Get items by lab test.
     *
     * @param string $testUuid
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemsByLabTest(string $testUuid, array $filters = []): array;

    /**
     * Get pending items.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getPendingItems(?int $facilityId = null): array;

    /**
     * Get items with abnormal results.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemsWithAbnormalResults(?int $facilityId = null): array;

    /**
     * Get items awaiting verification.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemsAwaitingVerification(?int $facilityId = null): array;

    /**
     * Get item with results.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemWithResults(string $uuid): array;

    /**
     * Get item with full details.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getItemWithFullDetails(string $uuid): array;

    /**
     * Get turnaround time statistics.
     *
     * @param string $testUuid
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTurnaroundTimeStatistics(string $testUuid, string $startDate, string $endDate): array;

    /**
     * Bulk update items status.
     *
     * @param array $uuids
     * @param string $status
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function bulkUpdateItemsStatus(array $uuids, string $status): array;
}