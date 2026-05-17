<?php

namespace App\Services\Contracts;

use App\Models\InventoryLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for InventoryLedger business logic.
 * Defines the contract for business operations.
 */
interface InventoryLedgerServiceInterface
{
    /**
     * Get all inventory ledger entries with filters.
     *
     * @param array $filters
     * @param array $with
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllLedgerEntries(array $filters = [], array $with = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get a specific ledger entry by ID.
     *
     * @param int $id
     * @param array $with
     * @return InventoryLedger
     */
    public function getLedgerEntryById(int $id, array $with = []): InventoryLedger;

    /**
     * Get a specific ledger entry by UUID.
     *
     * @param string $uuid
     * @param array $with
     * @return InventoryLedger
     */
    public function getLedgerEntryByUuid(string $uuid, array $with = []): InventoryLedger;

    /**
     * Create a new inventory ledger entry.
     * This is the main method for recording inventory transactions.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function createLedgerEntry(array $data): InventoryLedger;

    /**
     * Update an existing ledger entry (for corrections only).
     *
     * @param int $id
     * @param array $data
     * @return InventoryLedger
     */
    public function updateLedgerEntry(int $id, array $data): InventoryLedger;

    /**
     * Delete a ledger entry (extremely rare - for data integrity fixes).
     *
     * @param int $id
     * @return bool
     */
    public function deleteLedgerEntry(int $id): bool;

    /**
     * Verify a ledger entry.
     *
     * @param int $id
     * @param int $verifiedByStaffId
     * @param string|null $notes
     * @return InventoryLedger
     */
    public function verifyLedgerEntry(int $id, int $verifiedByStaffId, ?string $notes = null): InventoryLedger;

    /**
     * Get current inventory balance for an item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @return float
     */
    public function getCurrentBalance(int $facilityId, int $inventoryItemId): float;

    /**
     * Get inventory movement history for an item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getInventoryHistory(int $facilityId, int $inventoryItemId, array $filters = [], array $with = []): Collection;

    /**
     * Record a purchase transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordPurchase(array $data): InventoryLedger;

    /**
     * Record a consumption transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordConsumption(array $data): InventoryLedger;

    /**
     * Record an adjustment transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordAdjustment(array $data): InventoryLedger;

    /**
     * Record a transfer transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordTransfer(array $data): InventoryLedger;

    /**
     * Generate transaction hash for integrity verification.
     *
     * @param array $data
     * @return string
     */
    public function generateTransactionHash(array $data): string;

    /**
     * Validate inventory transaction business rules.
     *
     * @param array $data
     * @return array
     */
    public function validateTransaction(array $data): array;
}