<?php

namespace App\Repositories\InventoryLedger;

use App\Models\InventoryLedger;
use App\Repositories\Contracts\InventoryLedgerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository implementation for InventoryLedger.
 * Handles all database interactions for inventory ledger entries.
 */
class InventoryLedgerRepository implements InventoryLedgerRepositoryInterface
{
    /**
     * Get all inventory ledger entries with pagination.
     *
     * @param array $filters
     * @param array $with
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], array $with = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = InventoryLedger::with($with);
            
            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }
            
            if (!empty($filters['inventory_item_id'])) {
                $query->where('inventory_item_id', $filters['inventory_item_id']);
            }
            
            if (!empty($filters['transaction_type'])) {
                $query->where('transaction_type', $filters['transaction_type']);
            }
            
            if (!empty($filters['transaction_cause'])) {
                $query->where('transaction_cause', $filters['transaction_cause']);
            }
            
            if (!empty($filters['lot_number'])) {
                $query->where('lot_number', $filters['lot_number']);
            }
            
            if (!empty($filters['start_date'])) {
                $query->where('transaction_timestamp', '>=', $filters['start_date']);
            }
            
            if (!empty($filters['end_date'])) {
                $query->where('transaction_timestamp', '<=', $filters['end_date']);
            }
            
            if (!empty($filters['verified_only'])) {
                $query->whereNotNull('verified_at');
            }
            
            // Order by transaction timestamp (newest first)
            $query->orderBy('transaction_timestamp', 'desc');
            
            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory ledger entries', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            
            throw $e;
        }
    }

    /**
     * Find a ledger entry by ID.
     *
     * @param int $id
     * @param array $with
     * @return InventoryLedger|null
     */
    public function findById(int $id, array $with = []): ?InventoryLedger
    {
        try {
            return InventoryLedger::with($with)->find($id);
        } catch (ModelNotFoundException $e) {
            Log::warning('Inventory ledger entry not found', [
                'id' => $id,
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to find inventory ledger entry by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            throw $e;
        }
    }

    /**
     * Find a ledger entry by transaction UUID.
     *
     * @param string $uuid
     * @param array $with
     * @return InventoryLedger|null
     */
    public function findByUuid(string $uuid, array $with = []): ?InventoryLedger
    {
        try {
            return InventoryLedger::with($with)->where('transaction_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find inventory ledger entry by UUID', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a new ledger entry.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function create(array $data): InventoryLedger
    {
        try {
            return InventoryLedger::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create inventory ledger entry', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing ledger entry.
     * Note: Ledger entries are typically immutable, but this allows for corrections.
     *
     * @param int $id
     * @param array $data
     * @return InventoryLedger
     */
    public function update(int $id, array $data): InventoryLedger
    {
        try {
            $ledgerEntry = $this->findById($id);
            
            if (!$ledgerEntry) {
                throw new ModelNotFoundException("Inventory ledger entry with ID {$id} not found");
            }
            
            $ledgerEntry->update($data);
            
            return $ledgerEntry->fresh();
        } catch (ModelNotFoundException $e) {
            Log::warning('Attempted to update non-existent inventory ledger entry', [
                'id' => $id,
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a ledger entry.
     * Note: Use with extreme caution - ledger entries should be immutable.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $ledgerEntry = $this->findById($id);
            
            if (!$ledgerEntry) {
                throw new ModelNotFoundException("Inventory ledger entry with ID {$id} not found");
            }
            
            return $ledgerEntry->delete();
        } catch (ModelNotFoundException $e) {
            Log::warning('Attempted to delete non-existent inventory ledger entry', [
                'id' => $id,
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to delete inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            throw $e;
        }
    }

    /**
     * Get ledger entries by facility and item.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @param array $filters
     * @return Collection
     */
    public function getByFacilityAndItem(int $facilityId, int $inventoryItemId, array $filters = []): Collection
    {
        try {
            $query = InventoryLedger::where('facility_id', $facilityId)
                ->where('inventory_item_id', $inventoryItemId);
            
            if (!empty($filters['transaction_type'])) {
                $query->where('transaction_type', $filters['transaction_type']);
            }
            
            if (!empty($filters['start_date'])) {
                $query->where('transaction_timestamp', '>=', $filters['start_date']);
            }
            
            if (!empty($filters['end_date'])) {
                $query->where('transaction_timestamp', '<=', $filters['end_date']);
            }
            
            $query->orderBy('transaction_timestamp', 'asc');
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get ledger entries by facility and item', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
                'inventory_item_id' => $inventoryItemId,
            ]);
            
            throw $e;
        }
    }

    /**
     * Get current balance for an inventory item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @return float
     */
    public function getCurrentBalance(int $facilityId, int $inventoryItemId): float
    {
        try {
            $latestEntry = InventoryLedger::where('facility_id', $facilityId)
                ->where('inventory_item_id', $inventoryItemId)
                ->latest('transaction_timestamp')
                ->first();
            
            return $latestEntry ? (float) $latestEntry->balance_after_transaction : 0.0;
        } catch (\Exception $e) {
            Log::error('Failed to get current balance', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
                'inventory_item_id' => $inventoryItemId,
            ]);
            
            throw $e;
        }
    }

    /**
     * Get ledger entries with lot number tracking.
     *
     * @param string $lotNumber
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByLotNumber(string $lotNumber, ?int $facilityId = null): Collection
    {
        try {
            $query = InventoryLedger::where('lot_number', $lotNumber);
            
            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }
            
            $query->orderBy('expiry_date', 'asc')
                  ->orderBy('transaction_timestamp', 'asc');
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get ledger entries by lot number', [
                'error' => $e->getMessage(),
                'lot_number' => $lotNumber,
                'facility_id' => $facilityId,
            ]);
            
            throw $e;
        }
    }

    /**
     * Get transactions within a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $facilityId
     * @return Collection
     */
    public function getTransactionsByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection
    {
        try {
            $query = InventoryLedger::whereBetween('transaction_timestamp', [$startDate, $endDate]);
            
            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }
            
            $query->orderBy('transaction_timestamp', 'asc');
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get transactions by date range', [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'facility_id' => $facilityId,
            ]);
            
            throw $e;
        }
    }
}