<?php

namespace App\Repositories\Contracts;

use App\Models\InventoryLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for InventoryLedger repository operations.
 * Defines the contract for data persistence layer.
 */
interface InventoryLedgerRepositoryInterface
{
    /**
     * Get all inventory ledger entries with pagination.
     *
     * @param array $filters
     * @param array $with
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], array $with = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a ledger entry by ID.
     *
     * @param int $id
     * @param array $with
     * @return InventoryLedger|null
     */
    public function findById(int $id, array $with = []): ?InventoryLedger;

    /**
     * Find a ledger entry by transaction UUID.
     *
     * @param string $uuid
     * @param array $with
     * @return InventoryLedger|null
     */
    public function findByUuid(string $uuid, array $with = []): ?InventoryLedger;

    /**
     * Create a new ledger entry.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function create(array $data): InventoryLedger;

    /**
     * Update an existing ledger entry.
     * Note: Ledger entries are typically immutable, but this allows for corrections.
     *
     * @param int $id
     * @param array $data
     * @return InventoryLedger
     */
    public function update(int $id, array $data): InventoryLedger;

    /**
     * Delete a ledger entry.
     * Note: Use with extreme caution - ledger entries should be immutable.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get ledger entries by facility and item.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getByFacilityAndItem(int $facilityId, int $inventoryItemId, array $filters = [], array $with = []): Collection;

    /**
     * Get current balance for an inventory item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @return float
     */
    public function getCurrentBalance(int $facilityId, int $inventoryItemId): float;

    /**
     * Get ledger entries with lot number tracking.
     *
     * @param string $lotNumber
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByLotNumber(string $lotNumber, ?int $facilityId = null): Collection;

    /**
     * Get transactions within a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $facilityId
     * @return Collection
     */
    public function getTransactionsByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection;
}