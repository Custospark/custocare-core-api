<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabRequestItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabRequestItemRepositoryInterface
{
    /**
     * Find item by ID.
     *
     * @param int $id
     * @return LabRequestItem|null
     */
    public function findById(int $id): ?LabRequestItem;

    /**
     * Find item by UUID.
     *
     * @param string $uuid
     * @return LabRequestItem|null
     */
    public function findByUuid(string $uuid): ?LabRequestItem;

    /**
     * Find item by sample identifier.
     *
     * @param string $sampleIdentifier
     * @return LabRequestItem|null
     */
    public function findBySampleIdentifier(string $sampleIdentifier): ?LabRequestItem;

    /**
     * Get all items with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get items by lab request.
     *
     * @param int $labRequestId
     * @param array $filters
     * @return Collection
     */
    public function getByLabRequest(int $labRequestId, array $filters = []): Collection;

    /**
     * Get items by lab test.
     *
     * @param int $labTestId
     * @param array $filters
     * @return Collection
     */
    public function getByLabTest(int $labTestId, array $filters = []): Collection;

    /**
     * Get items by status.
     *
     * @param string $status
     * @param int|null $labRequestId
     * @return Collection
     */
    public function getByStatus(string $status, ?int $labRequestId = null): Collection;

    /**
     * Get pending items.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getPendingItems(?int $facilityId = null): Collection;

    /**
     * Get items with abnormal or critical results.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getAbnormalOrCriticalItems(?int $facilityId = null): Collection;

    /**
     * Get items awaiting verification.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getItemsAwaitingVerification(?int $facilityId = null): Collection;

    /**
     * Create a new item.
     *
     * @param array $data
     * @return LabRequestItem
     */
    public function create(array $data): LabRequestItem;

    /**
     * Bulk create items for a lab request.
     *
     * @param int $labRequestId
     * @param array $items
     * @return Collection
     */
    public function bulkCreate(int $labRequestId, array $items): array;

    /**
     * Update an existing item.
     *
     * @param LabRequestItem $item
     * @param array $data
     * @return bool
     */
    public function update(LabRequestItem $item, array $data): bool;

    /**
     * Delete an item (soft delete).
     *
     * @param LabRequestItem $item
     * @return bool
     */
    public function delete(LabRequestItem $item): bool;

    /**
     * Restore a soft-deleted item.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Update item status.
     *
     * @param LabRequestItem $item
     * @param string $status
     * @return bool
     */
    public function updateStatus(LabRequestItem $item, string $status): bool;

    /**
     * Mark sample as collected.
     *
     * @param LabRequestItem $item
     * @param int $collectedByStaffId
     * @param string|null $sampleIdentifier
     * @return bool
     */
    public function markSampleCollected(LabRequestItem $item, int $collectedByStaffId, ?string $sampleIdentifier = null): bool;

    /**
     * Mark item as verified.
     *
     * @param LabRequestItem $item
     * @param int $verifiedByStaffId
     * @return bool
     */
    public function markVerified(LabRequestItem $item, int $verifiedByStaffId): bool;

    /**
     * Cancel item.
     *
     * @param LabRequestItem $item
     * @param string $reason
     * @param int|null $cancelledByStaffId
     * @return bool
     */
    public function cancel(LabRequestItem $item, string $reason, ?int $cancelledByStaffId = null): bool;

    /**
     * Get item with its results.
     *
     * @param int $id
     * @return LabRequestItem|null
     */
    public function getWithResults(int $id): ?LabRequestItem;

    /**
     * Get item with full details (request, test, results).
     *
     * @param int $id
     * @return LabRequestItem|null
     */
    public function getWithFullDetails(int $id): ?LabRequestItem;

    /**
     * Get items by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection;

    /**
     * Get turnaround time statistics.
     *
     * @param int $labTestId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getTurnaroundTimeStatistics(int $labTestId, string $startDate, string $endDate): array;
}