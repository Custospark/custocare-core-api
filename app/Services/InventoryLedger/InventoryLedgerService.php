<?php

namespace App\Services\InventoryLedger;

use App\Models\InventoryLedger;
use App\Repositories\Contracts\InventoryLedgerRepositoryInterface;
use App\Services\Contracts\InventoryLedgerServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service implementation for InventoryLedger business logic.
 * Contains all business rules and transaction orchestration.
 */
class InventoryLedgerService implements InventoryLedgerServiceInterface
{
    /**
     * The repository instance.
     *
     * @var InventoryLedgerRepositoryInterface
     */
    protected InventoryLedgerRepositoryInterface $repository;

    /**
     * Create a new service instance.
     *
     * @param InventoryLedgerRepositoryInterface $repository
     */
    public function __construct(InventoryLedgerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all inventory ledger entries with filters.
     *
     * @param array $filters
     * @param array $with
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllLedgerEntries(array $filters = [], array $with = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            return $this->repository->getAll($filters, $with, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve ledger entries in service', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            
            throw new \RuntimeException('Unable to retrieve ledger entries. Please try again later.');
        }
    }

    /**
     * Get a specific ledger entry by ID.
     *
     * @param int $id
     * @param array $with
     * @return InventoryLedger
     */
    public function getLedgerEntryById(int $id, array $with = []): InventoryLedger
    {
        try {
            $entry = $this->repository->findById($id, $with);
            
            if (!$entry) {
                throw new \RuntimeException('Ledger entry not found.');
            }
            
            return $entry;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to find ledger entry by ID in service', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            throw new \RuntimeException('Unable to retrieve ledger entry. Please try again later.');
        }
    }

    /**
     * Get a specific ledger entry by UUID.
     *
     * @param string $uuid
     * @param array $with
     * @return InventoryLedger
     */
    public function getLedgerEntryByUuid(string $uuid, array $with = []): InventoryLedger
    {
        try {
            $entry = $this->repository->findByUuid($uuid, $with);
            
            if (!$entry) {
                throw new \RuntimeException('Ledger entry not found.');
            }
            
            return $entry;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to find ledger entry by UUID in service', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);
            
            throw new \RuntimeException('Unable to retrieve ledger entry. Please try again later.');
        }
    }

    /**
     * Create a new inventory ledger entry.
     * This is the main method for recording inventory transactions.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function createLedgerEntry(array $data): InventoryLedger
    {
        DB::beginTransaction();
        
        try {
            // Validate business rules
            $validation = $this->validateTransaction($data);
            
            if (!$validation['valid']) {
                throw new ValidationException(
                    Validator::make([], []), 
                    null, 
                    $validation['errors']
                );
            }
            
            // Calculate balance after transaction
            $currentBalance = $this->repository->getCurrentBalance(
                $data['facility_id'],
                $data['inventory_item_id']
            );
            
            $newBalance = $currentBalance + $data['quantity_change'];
            
            // Ensure balance doesn't go negative (unless it's an adjustment or specific types)
            $disallowNegative = !in_array($data['transaction_type'], [
                'adjustment_decrease',
                'consumption_visit',
                'consumption_waste',
                'transfer_out',
                'expired',
                'damaged',
                'stolen',
                'recalled'
            ]);
            
            if ($disallowNegative && $newBalance < 0) {
                throw new \RuntimeException('Insufficient inventory. Current balance: ' . $currentBalance);
            }
            
            // Generate transaction hash
            $transactionData = array_merge($data, [
                'balance_after_transaction' => $newBalance,
                'transaction_uuid' => $data['transaction_uuid'] ?? Str::uuid()->toString(),
                'transaction_timestamp' => $data['transaction_timestamp'] ?? now(),
                'created_at' => now(),
            ]);
            
            $transactionData['transaction_hash'] = $this->generateTransactionHash($transactionData);
            
            // Create the ledger entry
            $ledgerEntry = $this->repository->create($transactionData);
            
            DB::commit();
            
            Log::info('Inventory ledger entry created successfully', [
                'id' => $ledgerEntry->id,
                'transaction_uuid' => $ledgerEntry->transaction_uuid,
                'facility_id' => $ledgerEntry->facility_id,
                'inventory_item_id' => $ledgerEntry->inventory_item_id,
                'transaction_type' => $ledgerEntry->transaction_type,
                'quantity_change' => $ledgerEntry->quantity_change,
                'new_balance' => $ledgerEntry->balance_after_transaction,
            ]);
            
            return $ledgerEntry;
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create inventory ledger entry in service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            
            throw new \RuntimeException('Failed to create ledger entry. Please try again.');
        }
    }

    /**
     * Update an existing ledger entry (for corrections only).
     *
     * @param int $id
     * @param array $data
     * @return InventoryLedger
     */
    public function updateLedgerEntry(int $id, array $data): InventoryLedger
    {
        DB::beginTransaction();
        
        try {
            // Get existing entry
            $existingEntry = $this->getLedgerEntryById($id);
            
            // Validate that we can update this entry
            if ($existingEntry->verified_at) {
                throw new \RuntimeException('Cannot update a verified ledger entry.');
            }
            
            // Recalculate hash if data changed
            if (isset($data['transaction_hash'])) {
                unset($data['transaction_hash']);
            }
            
            // Generate new hash
            $updateData = array_merge($existingEntry->toArray(), $data);
            $updateData['transaction_hash'] = $this->generateTransactionHash($updateData);
            
            $ledgerEntry = $this->repository->update($id, $updateData);
            
            DB::commit();
            
            Log::info('Inventory ledger entry updated', [
                'id' => $id,
                'updated_fields' => array_keys($data),
            ]);
            
            return $ledgerEntry;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update ledger entry in service', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);
            
            throw new \RuntimeException('Failed to update ledger entry. Please try again.');
        }
    }

    /**
     * Delete a ledger entry (extremely rare - for data integrity fixes).
     *
     * @param int $id
     * @return bool
     */
    public function deleteLedgerEntry(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            // Get existing entry
            $existingEntry = $this->getLedgerEntryById($id);
            
            // Validate that we can delete this entry
            if ($existingEntry->verified_at) {
                throw new \RuntimeException('Cannot delete a verified ledger entry.');
            }
            
            // Check if this is the latest entry for the item
            $latestEntry = InventoryLedger::where('facility_id', $existingEntry->facility_id)
                ->where('inventory_item_id', $existingEntry->inventory_item_id)
                ->latest('transaction_timestamp')
                ->first();
            
            if ($latestEntry && $latestEntry->id === $id) {
                throw new \RuntimeException('Cannot delete the most recent ledger entry for this inventory item.');
            }
            
            $result = $this->repository->delete($id);
            
            DB::commit();
            
            Log::warning('Inventory ledger entry deleted', [
                'id' => $id,
                'deleted_by' => auth::id() ?? 'system',
            ]);
            
            return $result;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete ledger entry in service', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            throw new \RuntimeException('Failed to delete ledger entry. Please try again.');
        }
    }

    /**
     * Verify a ledger entry.
     *
     * @param int $id
     * @param int $verifiedByStaffId
     * @param string|null $notes
     * @return InventoryLedger
     */
    public function verifyLedgerEntry(int $id, int $verifiedByStaffId, ?string $notes = null): InventoryLedger
    {
        DB::beginTransaction();
        
        try {
            $entry = $this->getLedgerEntryById($id);
            
            if ($entry->verified_at) {
                throw new \RuntimeException('This ledger entry has already been verified.');
            }
            
            $updateData = [
                'verified_by_staff_id' => $verifiedByStaffId,
                'verified_at' => now(),
            ];
            
            if ($notes) {
                $updateData['transaction_notes'] = $entry->transaction_notes 
                    ? $entry->transaction_notes . "\n\nVerification: " . $notes
                    : "Verification: " . $notes;
            }
            
            $verifiedEntry = $this->repository->update($id, $updateData);
            
            DB::commit();
            
            Log::info('Inventory ledger entry verified', [
                'id' => $id,
                'verified_by' => $verifiedByStaffId,
            ]);
            
            return $verifiedEntry;
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to verify ledger entry in service', [
                'error' => $e->getMessage(),
                'id' => $id,
                'verified_by' => $verifiedByStaffId,
            ]);
            
            throw new \RuntimeException('Failed to verify ledger entry. Please try again.');
        }
    }

    /**
     * Get current inventory balance for an item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @return float
     */
    public function getCurrentBalance(int $facilityId, int $inventoryItemId): float
    {
        try {
            return $this->repository->getCurrentBalance($facilityId, $inventoryItemId);
        } catch (\Exception $e) {
            Log::error('Failed to get current balance in service', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
                'inventory_item_id' => $inventoryItemId,
            ]);
            
            throw new \RuntimeException('Unable to retrieve current balance. Please try again later.');
        }
    }

    /**
     * Get inventory movement history for an item at a facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @param array $filters
     * @return Collection
     */
    public function getInventoryHistory(int $facilityId, int $inventoryItemId, array $filters = [], array $with = []): Collection
    {
        try {
            return $this->repository->getByFacilityAndItem($facilityId, $inventoryItemId, $filters, $with);
        } catch (\Exception $e) {
            Log::error('Failed to get inventory history in service', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
                'inventory_item_id' => $inventoryItemId,
                'filters' => $filters,
            ]);
            
            throw new \RuntimeException('Unable to retrieve inventory history. Please try again later.');
        }
    }

    /**
     * Record a purchase transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordPurchase(array $data): InventoryLedger
    {
        $transactionData = array_merge($data, [
            'transaction_type' => 'purchase',
            'transaction_cause' => $data['transaction_cause'] ?? 'manual_entry',
        ]);
        
        // Ensure quantity is positive for purchases
        if ($transactionData['quantity_change'] <= 0) {
            throw new \RuntimeException('Purchase quantity must be positive.');
        }
        
        return $this->createLedgerEntry($transactionData);
    }

    /**
     * Record a consumption transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordConsumption(array $data): InventoryLedger
    {
        $transactionData = array_merge($data, [
            'transaction_type' => $data['consumption_type'] ?? 'consumption_visit',
            'transaction_cause' => 'patient_use',
            'quantity_change' => -abs($data['quantity']), // Ensure negative
        ]);
        
        return $this->createLedgerEntry($transactionData);
    }

    /**
     * Record an adjustment transaction.
     *
     * @param array $data
     * @return InventoryLedger
     */
    public function recordAdjustment(array $data): InventoryLedger
    {
        $quantity = $data['quantity'];
        $adjustmentType = $quantity > 0 ? 'adjustment_increase' : 'adjustment_decrease';
        
        $transactionData = array_merge($data, [
            'transaction_type' => $adjustmentType,
            'quantity_change' => $quantity,
        ]);

        if (!isset($transactionData['transaction_cause'])) {
            $transactionData['transaction_cause'] = 'reconciliation';
        }
        
        return $this->createLedgerEntry($transactionData);
    }

    /**
     * Record a transfer transaction.
     * This creates two entries: one for transfer_out and one for transfer_in.
     *
     * @param array $data
     * @return array
     */
    public function recordTransfer(array $data): InventoryLedger
    {
        if (!isset($data['transfer_from_facility_id']) || !isset($data['transfer_to_facility_id'])) {
            throw new \RuntimeException('Both source and destination facilities are required for transfers.');
        }
        
        if ($data['transfer_from_facility_id'] === $data['transfer_to_facility_id']) {
            throw new \RuntimeException('Source and destination facilities cannot be the same.');
        }
        
        $quantity = abs($data['quantity']);
        
        // Create transfer out entry
        $transferOutData = array_merge($data, [
            'facility_id' => $data['transfer_from_facility_id'],
            'transaction_type' => 'transfer_out',
            'transaction_cause' => 'administrative',
            'quantity_change' => -$quantity,
            'transfer_to_facility_id' => $data['transfer_to_facility_id'],
        ]);
        
        $transferOut = $this->createLedgerEntry($transferOutData);
        
        // Create transfer in entry
        $transferInData = array_merge($data, [
            'facility_id' => $data['transfer_to_facility_id'],
            'transaction_type' => 'transfer_in',
            'transaction_cause' => 'administrative',
            'quantity_change' => $quantity,
            'transfer_from_facility_id' => $data['transfer_from_facility_id'],
            'reference_document_number' => $transferOut->transaction_uuid,
        ]);
        
        $transferIn = $this->createLedgerEntry($transferInData);
        
        return $transferIn;
    }

    /**
     * Generate transaction hash for integrity verification.
     *
     * @param array $data
     * @return string
     */
    public function generateTransactionHash(array $data): string
    {
        // Remove any existing hash
        unset($data['transaction_hash']);
        
        // Sort array by keys for consistent hashing
        ksort($data);
        
        // Convert to JSON and hash
        $jsonString = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        return hash('sha256', $jsonString);
    }

    /**
     * Validate inventory transaction business rules.
     *
     * @param array $data
     * @return array
     */
    public function validateTransaction(array $data): array
    {
        $errors = [];
        
        // Required fields
        $requiredFields = [
            'facility_id',
            'inventory_item_id',
            'transaction_type',
            'quantity_change',
            'unit_of_measure',
            'transaction_cause',
            'performed_by_staff_id',
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = ["The {$field} field is required."];
            }
        }
        
        // Validate transaction type
        $validTypes = [
            'purchase',
            'adjustment_increase',
            'adjustment_decrease',
            'consumption_visit',
            'consumption_waste',
            'return_to_supplier',
            'transfer_in',
            'transfer_out',
            'cycle_count',
            'expired',
            'damaged',
            'stolen',
            'recalled'
        ];
        
        if (isset($data['transaction_type']) && !in_array($data['transaction_type'], $validTypes)) {
            $errors['transaction_type'] = ["Invalid transaction type."];
        }
        
        // Validate transaction cause
        $validCauses = [
            'manual_entry',
            'system_automated',
            'physical_count',
            'reconciliation',
            'patient_use',
            'procedural_use',
            'administrative'
        ];
        
        if (isset($data['transaction_cause']) && !in_array($data['transaction_cause'], $validCauses)) {
            $errors['transaction_cause'] = ["Invalid transaction cause."];
        }
        
        // Validate quantity is numeric
        if (isset($data['quantity_change']) && !is_numeric($data['quantity_change'])) {
            $errors['quantity_change'] = ["Quantity must be a number."];
        }
        
        // Validate dates if provided
        if (isset($data['expiry_date']) && !strtotime($data['expiry_date'])) {
            $errors['expiry_date'] = ["Invalid expiry date format."];
        }
        
        if (isset($data['manufacture_date']) && !strtotime($data['manufacture_date'])) {
            $errors['manufacture_date'] = ["Invalid manufacture date format."];
        }
        
        // Validate cost fields if provided
        if (isset($data['unit_cost_at_transaction']) && !is_numeric($data['unit_cost_at_transaction'])) {
            $errors['unit_cost_at_transaction'] = ["Unit cost must be a number."];
        }
        
        if (isset($data['total_cost']) && !is_numeric($data['total_cost'])) {
            $errors['total_cost'] = ["Total cost must be a number."];
        }
        
        // Validate transfer logic
        if (isset($data['transaction_type']) && in_array($data['transaction_type'], ['transfer_in', 'transfer_out'])) {
            if ($data['transaction_type'] === 'transfer_in' && empty($data['transfer_from_facility_id'])) {
                $errors['transfer_from_facility_id'] = ["Source facility is required for transfer in."];
            }
            
            if ($data['transaction_type'] === 'transfer_out' && empty($data['transfer_to_facility_id'])) {
                $errors['transfer_to_facility_id'] = ["Destination facility is required for transfer out."];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}