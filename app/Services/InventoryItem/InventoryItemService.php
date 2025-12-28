<?php

namespace App\Services\InventoryItem;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use App\Services\Contracts\InventoryItemServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryItemService implements InventoryItemServiceInterface
{
    /**
     * @var InventoryItemRepositoryInterface
     */
    protected $inventoryItemRepository;

    /**
     * InventoryItemService constructor.
     *
     * @param InventoryItemRepositoryInterface $inventoryItemRepository
     */
    public function __construct(InventoryItemRepositoryInterface $inventoryItemRepository)
    {
        $this->inventoryItemRepository = $inventoryItemRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllInventoryItems(array $filters = [], int $perPage = 15): array
    {
        try {
            $items = $this->inventoryItemRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'data' => $items,
                'message' => 'Inventory items retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve inventory items. Please try again later.',
                'error_code' => 'INVENTORY_ITEMS_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInventoryItemByUuid(string $uuid): array
    {
        try {
            $item = $this->inventoryItemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found',
                    'error_code' => 'INVENTORY_ITEM_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $item,
                'message' => 'Inventory item retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInventoryItemByCode(string $itemCode): array
    {
        try {
            $item = $this->inventoryItemRepository->findByItemCode($itemCode);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found',
                    'error_code' => 'INVENTORY_ITEM_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $item,
                'message' => 'Inventory item retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item by code', [
                'item_code' => $itemCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createInventoryItem(array $data): array
    {
        // Validate item data
        $validationResult = $this->validateItemData($data);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
                'errors' => $validationResult['errors'] ?? null,
                'error_code' => 'INVENTORY_ITEM_VALIDATION_FAILED'
            ];
        }

        DB::beginTransaction();
        
        try {
            // Set created by staff if authenticated
            if (Auth::check()) {
                $data['created_by_staff_id'] = Auth::id();
            }

            // Handle JSON fields
            $data = $this->processJsonFields($data);

            $item = $this->inventoryItemRepository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $item,
                'message' => 'Inventory item created successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create inventory item', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_CREATION_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateInventoryItem(string $uuid, array $data): array
    {
        // Find the item
        $item = $this->inventoryItemRepository->findByUuid($uuid);
        
        if (!$item) {
            return [
                'success' => false,
                'message' => 'Inventory item not found',
                'error_code' => 'INVENTORY_ITEM_NOT_FOUND'
            ];
        }

        // Validate item data
        $validationResult = $this->validateItemData($data, $item->id);
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message'],
                'errors' => $validationResult['errors'] ?? null,
                'error_code' => 'INVENTORY_ITEM_VALIDATION_FAILED'
            ];
        }

        DB::beginTransaction();
        
        try {
            // Handle JSON fields
            $data = $this->processJsonFields($data);

            $updated = $this->inventoryItemRepository->update($item, $data);
            
            if (!$updated) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to update inventory item',
                    'error_code' => 'INVENTORY_ITEM_UPDATE_FAILED'
                ];
            }
            
            DB::commit();
            
            // Refresh the item
            $item->refresh();
            
            return [
                'success' => true,
                'data' => $item,
                'message' => 'Inventory item updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update inventory item', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_UPDATE_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteInventoryItem(string $uuid): array
    {
        // Find the item
        $item = $this->inventoryItemRepository->findByUuid($uuid);
        
        if (!$item) {
            return [
                'success' => false,
                'message' => 'Inventory item not found',
                'error_code' => 'INVENTORY_ITEM_NOT_FOUND'
            ];
        }

        try {
            $deleted = $this->inventoryItemRepository->delete($item);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete inventory item',
                    'error_code' => 'INVENTORY_ITEM_DELETION_FAILED'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Inventory item deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete inventory item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_DELETION_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreInventoryItem(string $uuid): array
    {
        // Find the item (including trashed)
        $item = InventoryItem::withTrashed()->where('item_uuid', $uuid)->first();
        
        if (!$item) {
            return [
                'success' => false,
                'message' => 'Inventory item not found',
                'error_code' => 'INVENTORY_ITEM_NOT_FOUND'
            ];
        }

        try {
            $restored = $this->inventoryItemRepository->restore($item);
            
            if (!$restored) {
                return [
                    'success' => false,
                    'message' => 'Failed to restore inventory item',
                    'error_code' => 'INVENTORY_ITEM_RESTORATION_FAILED'
                ];
            }
            
            return [
                'success' => true,
                'data' => $item,
                'message' => 'Inventory item restored successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restore inventory item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to restore inventory item. Please try again later.',
                'error_code' => 'INVENTORY_ITEM_RESTORATION_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInventoryItemsByCategory(string $category, array $filters = []): array
    {
        try {
            // Validate category
            $validCategories = [
                'medication', 'medical_supply', 'surgical_instrument', 'diagnostic_equipment',
                'implantable_device', 'prosthetic', 'laboratory_reagent',
                'personal_protective_equipment', 'administrative_supply', 'other'
            ];
            
            if (!in_array($category, $validCategories)) {
                return [
                    'success' => false,
                    'message' => 'Invalid category specified',
                    'error_code' => 'INVALID_CATEGORY'
                ];
            }
            
            $items = $this->inventoryItemRepository->getByCategory($category, $filters);
            
            return [
                'success' => true,
                'data' => $items,
                'message' => 'Inventory items retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory items by category', [
                'category' => $category,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve inventory items. Please try again later.',
                'error_code' => 'INVENTORY_ITEMS_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getControlledSubstances(array $filters = []): array
    {
        try {
            $items = $this->inventoryItemRepository->getControlledSubstances($filters);
            
            return [
                'success' => true,
                'data' => $items,
                'message' => 'Controlled substances retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve controlled substances', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve controlled substances. Please try again later.',
                'error_code' => 'CONTROLLED_SUBSTANCES_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSpecialHandlingItems(array $filters = []): array
    {
        try {
            $items = $this->inventoryItemRepository->getSpecialHandlingItems($filters);
            
            return [
                'success' => true,
                'data' => $items,
                'message' => 'Special handling items retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve special handling items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve special handling items. Please try again later.',
                'error_code' => 'SPECIAL_HANDLING_ITEMS_RETRIEVAL_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function searchInventoryItems(string $searchTerm, array $filters = [], int $perPage = 15): array
    {
        try {
            if (empty($searchTerm)) {
                return [
                    'success' => false,
                    'message' => 'Search term is required',
                    'error_code' => 'SEARCH_TERM_REQUIRED'
                ];
            }
            
            $items = $this->inventoryItemRepository->search($searchTerm, $filters, $perPage);
            
            return [
                'success' => true,
                'data' => $items,
                'message' => 'Search completed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search inventory items', [
                'search_term' => $searchTerm,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to search inventory items. Please try again later.',
                'error_code' => 'INVENTORY_ITEMS_SEARCH_FAILED'
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateItemData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        // Validate item code uniqueness
        if (isset($data['item_code'])) {
            if ($this->inventoryItemRepository->itemCodeExists($data['item_code'], $excludeId)) {
                $errors['item_code'] = ['The item code is already taken.'];
            }
        }

        // Validate NDC code uniqueness for medications
        if (isset($data['ndc_code']) && !empty($data['ndc_code'])) {
            if ($this->inventoryItemRepository->ndcCodeExists($data['ndc_code'], $excludeId)) {
                $errors['ndc_code'] = ['The NDC code is already registered.'];
            }
        }

        // Validate category
        if (isset($data['item_category'])) {
            $validCategories = [
                'medication', 'medical_supply', 'surgical_instrument', 'diagnostic_equipment',
                'implantable_device', 'prosthetic', 'laboratory_reagent',
                'personal_protective_equipment', 'administrative_supply', 'other'
            ];
            
            if (!in_array($data['item_category'], $validCategories)) {
                $errors['item_category'] = ['Invalid item category.'];
            }
        }

        // Validate status
        if (isset($data['status'])) {
            $validStatuses = ['active', 'inactive', 'discontinued', 'recalled'];
            
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = ['Invalid status.'];
            }
        }

        // Validate controlled substance schedule
        if (isset($data['controlled_substance_schedule'])) {
            $validSchedules = ['I', 'II', 'III', 'IV', 'V', 'non_controlled'];
            
            if (!in_array($data['controlled_substance_schedule'], $validSchedules)) {
                $errors['controlled_substance_schedule'] = ['Invalid controlled substance schedule.'];
            }
        }

        // Validate numeric fields
        $numericFields = [
            'unit_cost' => 10,
            'average_wholesale_price' => 10,
            'package_quantity' => 5,
            'reorder_point' => 5,
            'reorder_quantity' => 5,
            'safety_stock_level' => 5,
            'max_stock_level' => 5
        ];

        foreach ($numericFields as $field => $maxDigits) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[$field] = ["The {$field} must be a number."];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'message' => empty($errors) ? 'Validation passed' : 'Validation failed'
        ];
    }

    /**
     * Process JSON fields for storage.
     *
     * @param  array  $data
     * @return array
     */
    protected function processJsonFields(array $data): array
    {
        $jsonFields = [
            'active_ingredients',
            'storage_requirements',
            'regulatory_approvals',
            'safety_warnings',
            'contraindications',
            'metadata'
        ];

        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                try {
                    $decoded = json_decode($data[$field], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data[$field] = $decoded;
                    }
                } catch (\Exception $e) {
                    // If JSON is invalid, set to null
                    $data[$field] = null;
                }
            } elseif (isset($data[$field]) && is_array($data[$field])) {
                // Already an array, ensure it's JSON encodable
                $data[$field] = empty($data[$field]) ? null : $data[$field];
            }
        }

        return $data;
    }
}