<?php

declare(strict_types=1);

namespace App\Services\Prescription;

use App\Repositories\Contracts\PrescriptionItemRepositoryInterface;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrescriptionItemService
{
    protected PrescriptionItemRepositoryInterface $prescriptionItemRepository;
    protected PrescriptionRepositoryInterface $prescriptionRepository;

    public function __construct(
        PrescriptionItemRepositoryInterface $prescriptionItemRepository,
        PrescriptionRepositoryInterface $prescriptionRepository
    ) {
        $this->prescriptionItemRepository = $prescriptionItemRepository;
        $this->prescriptionRepository = $prescriptionRepository;
    }

    /**
     * Get all items for a prescription
     */
    public function getPrescriptionItems(int $prescriptionId): array
    {
        try {
            $prescription = $this->prescriptionRepository->find($prescriptionId);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found',
                    'data' => null
                ];
            }

            $items = $this->prescriptionItemRepository->getByPrescription($prescriptionId);

            return [
                'success' => true,
                'message' => 'Prescription items retrieved successfully',
                'data' => $items
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get prescription items: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve prescription items: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Create a new prescription item
     */
    public function createPrescriptionItem(int $prescriptionId, array $data, int $userId): array
    {
        try {
            $prescription = $this->prescriptionRepository->find($prescriptionId);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found',
                    'data' => null
                ];
            }

            DB::beginTransaction();

            $data['prescription_id'] = $prescriptionId;
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
            
            $item = $this->prescriptionItemRepository->create($data);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Medication added successfully',
                'data' => $item
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prescription item: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to add medication: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Update a prescription item
     */
    public function updatePrescriptionItem(int $id, array $data, int $userId): array
    {
        try {
            DB::beginTransaction();

            $data['updated_by'] = $userId;
            $updated = $this->prescriptionItemRepository->update($id, $data);

            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Prescription item not found',
                    'data' => null
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Medication updated successfully',
                'data' => $this->prescriptionItemRepository->getByPrescription($id)
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update prescription item: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update medication: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Delete a prescription item
     */
    public function deletePrescriptionItem(int $id): array
    {
        try {
            DB::beginTransaction();

            $deleted = $this->prescriptionItemRepository->delete($id);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Prescription item not found',
                    'data' => null
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Medication removed successfully',
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete prescription item: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to remove medication: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Bulk update prescription items (add, update, delete multiple at once)
     */
    public function bulkUpdatePrescriptionItems(int $prescriptionId, array $items, int $userId): array
    {
        try {
            $prescription = $this->prescriptionRepository->find($prescriptionId);
            
            if (!$prescription) {
                return [
                    'success' => false,
                    'message' => 'Prescription not found',
                    'data' => null
                ];
            }

            DB::beginTransaction();

            $updatedItems = [];
            
            foreach ($items as $item) {
                // Check if item has ID and _destroy flag
                if (isset($item['_destroy']) && $item['_destroy'] === true) {
                    if (isset($item['id'])) {
                        $this->prescriptionItemRepository->delete($item['id']);
                    }
                    continue;
                }
                
                // If item has ID, update existing
                if (isset($item['id'])) {
                    $item['updated_by'] = $userId;
                    $this->prescriptionItemRepository->update($item['id'], $item);
                    $updatedItems[] = $this->prescriptionItemRepository->getByPrescription($item['id']);
                } 
                // Otherwise create new
                else {
                    $item['prescription_id'] = $prescriptionId;
                    $item['created_by'] = $userId;
                    $item['updated_by'] = $userId;
                    $newItem = $this->prescriptionItemRepository->create($item);
                    $updatedItems[] = $newItem;
                }
            }

            DB::commit();

            $allItems = $this->prescriptionItemRepository->getByPrescription($prescriptionId);

            return [
                'success' => true,
                'message' => 'Medications updated successfully',
                'data' => $allItems
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update prescription items: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to update medications: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    public function store(int $prescriptionId, array $itemData, int $userId): array
{
    try {
        DB::beginTransaction();

        // Check if prescription exists
        $prescription = $this->prescriptionRepository->find($prescriptionId);
        
        if (!$prescription) {
            return [
                'success' => false,
                'message' => 'Prescription not found',
                'data' => null
            ];
        }

        // Check if prescription is still active/editable
        if (in_array($prescription->status, ['cancelled', 'dispensed'])) {
            return [
                'success' => false,
                'message' => 'Cannot add items to a ' . $prescription->status . ' prescription',
                'data' => null
            ];
        }

        // Prepare item data
        $itemData['prescription_id'] = $prescriptionId;
        $itemData['created_by'] = $userId;
        $itemData['updated_by'] = $userId;
        
        // Calculate total quantity
        $itemData['total_quantity'] = $this->calculateTotalQuantity(
            $itemData['dosage_quantity'],
            $itemData['duration_value'],
            $itemData['frequency']
        );

        // Create item
        $item = $this->prescriptionItemRepository->create($itemData);

        DB::commit();

        return [
            'success' => true,
            'message' => 'Prescription item added successfully',
            'data' => $item
        ];
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to add prescription item: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Failed to add prescription item: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

/**
 * Calculate total quantity based on dosage, duration and frequency
 */
private function calculateTotalQuantity(float $dosageQuantity, int $durationValue, string $frequency): float
{
    // Map frequency to times per day
    $frequencyMap = [
        'Once daily' => 1,
        'Twice daily' => 2,
        'Three times daily' => 3,
        'Four times daily' => 4,
        'Every 2 hours' => 12,
        'Every 4 hours' => 6,
        'Every 6 hours' => 4,
        'Every 8 hours' => 3,
        'Every 12 hours' => 2,
        'Once weekly' => 1/7,
        'Twice weekly' => 2/7,
    ];
    
    $timesPerDay = $frequencyMap[$frequency] ?? 1;
    
    // Total quantity = dosage quantity * times per day * duration in days
    return $dosageQuantity * $timesPerDay * $durationValue;
}
}