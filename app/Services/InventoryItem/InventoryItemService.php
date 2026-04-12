<?php

namespace App\Services\InventoryItem;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use App\Services\Contracts\InventoryItemServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service implementation for InventoryItem business logic.
 * All operations are scoped by facility_id from request headers.
 */
class InventoryItemService implements InventoryItemServiceInterface
{
    /**
     * The inventory item repository instance.
     *
     * @var InventoryItemRepositoryInterface
     */
    protected InventoryItemRepositoryInterface $repository;

    /**
     * Current facility ID from headers.
     *
     * @var int|null
     */
    protected ?int $facilityId = null;

    /**
     * Create a new service instance.
     *
     * @param InventoryItemRepositoryInterface $repository
     */
    public function __construct(InventoryItemRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->facilityId = $this->getCurrentFacilityId();
    }

    /**
     * Get current facility ID from request headers.
     *
     * @return int|null
     */
    protected function getCurrentFacilityId(): ?int
    {
        return request()->header('X-Facility-Id') 
            ? (int) request()->header('X-Facility-Id')
            : null;
    }

    /**
     * Validate facility ID is present.
     *
     * @return bool
     */
    protected function validateFacilityId(): bool
    {
        if (!$this->facilityId) {
            throw new \RuntimeException('Facility ID is required in request headers (X-Facility-Id)');
        }
        return true;
    }

    /**
     * Get all inventory items for current facility with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllInventoryItems(array $filters = [], int $perPage = 15): array
    {
        try {
            $this->validateFacilityId();
            
            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            
            $paginator = $this->repository->paginate($perPage, $filters);

            return [
                'success' => true,
                'message' => 'Inventory items retrieved successfully.',
                'data' => [
                    'items' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory items', [
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve inventory items at this time. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get an inventory item by UUID for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function getInventoryItemByUuid(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            $inventoryItem = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$inventoryItem) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found.',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => 'Inventory item retrieved successfully.',
                'data' => $inventoryItem
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item by UUID', [
                'uuid' => $uuid,
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve inventory item. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get an inventory item by item code for current facility.
     *
     * @param string $itemCode
     * @return array
     */
    public function getInventoryItemByCode(string $itemCode): array
    {
        try {
            $this->validateFacilityId();
            
            $inventoryItem = $this->repository->findByItemCodeAndFacility($itemCode, $this->facilityId);

            if (!$inventoryItem) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found.',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => 'Inventory item retrieved successfully.',
                'data' => $inventoryItem
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item by code', [
                'item_code' => $itemCode,
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve inventory item. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Create a new inventory item for current facility.
     *
     * @param array $data
     * @return array
     */
 public function createInventoryItem(array $data): array
{
    // Never allow callers to set auto-increment PK
    unset($data['id']);

    try {
        $this->validateFacilityId();
        
        /**
         * 1) Normalize + provide safe defaults BEFORE validation
         */

        // Auto-generate item_code if missing/empty OR if it already exists
        if (!isset($data['item_code']) || trim((string) $data['item_code']) === '') {
            $data['item_code'] = $this->generateUniqueItemCode($this->facilityId);
        } else {
            $data['item_code'] = trim((string) $data['item_code']);
            
            // Check if the provided item code already exists for this facility
            $existingItem = $this->findByFacilityAndCode(
                $this->facilityId, 
                $data['item_code']
            );
            
            if ($existingItem) {
                // Item code already exists, generate a new unique one
                Log::info('Item code already exists, generating new code', [
                    'facility_id' => $this->facilityId,
                    'requested_code' => $data['item_code']
                ]);
                
                $data['item_code'] = $this->generateUniqueItemCode($this->facilityId);
            }
        }

        // Ensure item_uuid exists
        if (!isset($data['item_uuid']) || trim((string) $data['item_uuid']) === '') {
            $data['item_uuid'] = (string) Str::uuid();
        } else {
            $data['item_uuid'] = trim((string) $data['item_uuid']);
        }

        // Set facility_id from headers (cannot be overridden)
        $data['facility_id'] = $this->facilityId;

        // Set default currency code
        if (!isset($data['currency_code']) || trim((string) $data['currency_code']) === '') {
            $data['currency_code'] = 'USD';
        } else {
            $data['currency_code'] = strtoupper(trim((string) $data['currency_code']));
        }

        // Set default unit of measure
        if (!isset($data['unit_of_measure']) || trim((string) $data['unit_of_measure']) === '') {
            $data['unit_of_measure'] = 'each';
        }

        // Set default package quantity
        if (!isset($data['package_quantity']) || $data['package_quantity'] < 1) {
            $data['package_quantity'] = 1;
        }

        // Set default boolean fields
        $defaultBooleans = [
            'requires_refrigeration' => false,
            'requires_controlled_access' => false,
            'requires_prescription' => false,
            'is_hazardous' => false,
            'is_billable' => true,
            'track_by_lot' => false,
            'track_by_serial' => false,
        ];

        foreach ($defaultBooleans as $field => $defaultValue) {
            if (!isset($data[$field])) {
                $data[$field] = $defaultValue;
            }
        }

        // Set default status
        if (!isset($data['status']) || trim((string) $data['status']) === '') {
            $data['status'] = 'active';
        }

        // Set created_by_staff_id if missing and user is authenticated
        if (!isset($data['created_by_staff_id']) && Auth::check()) {
            $data['created_by_staff_id'] = Auth::id();
        }

        // Process JSON fields
        $data = $this->processJsonFields($data);

        // Validate business rules before creation
        $validationResult = $this->validateInventoryItemData($data);
        if (!$validationResult['success']) {
            return $validationResult;
        }

        /**
         * 3) Validate numeric ranges
         */
        $numericValidations = [
            'unit_cost' => ['min' => 0, 'max' => 99999999.99],
            'average_wholesale_price' => ['min' => 0, 'max' => 99999999.99],
            'package_quantity' => ['min' => 1, 'max' => 65535],
            'reorder_point' => ['min' => 0, 'max' => 65535],
            'reorder_quantity' => ['min' => 1, 'max' => 65535],
            'safety_stock_level' => ['min' => 0, 'max' => 65535],
            'max_stock_level' => ['min' => 0, 'max' => 65535],
        ];

        foreach ($numericValidations as $field => $limits) {
            if (isset($data[$field]) && $data[$field] !== null) {
                if ($data[$field] < $limits['min'] || $data[$field] > $limits['max']) {
                    return [
                        'success' => false,
                        'message' => "{$field} must be between {$limits['min']} and {$limits['max']}.",
                        'data' => []
                    ];
                }
            }
        }

        /**
         * 4) Persist
         */
        $inventoryItem = DB::transaction(function () use ($data) {
            return $this->repository->create($data);
        });

        Log::info('Inventory item created successfully', [
            'facility_id' => $this->facilityId,
            'item_uuid' => $inventoryItem->item_uuid,
            'item_code' => $inventoryItem->item_code,
        ]);

        return [
            'success' => true,
            'message' => 'Inventory item created successfully.',
            'data' => $inventoryItem
        ];
    } catch (\Throwable $e) {
        Log::error('Failed to create inventory item', [
            'facility_id' => $this->facilityId,
            'data' => $this->sanitizeDataForLogging($data),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to create inventory item. Please try again.',
            'data' => []
        ];
    }
}

// In your repository class
    public function findByFacilityAndCode(int $facilityId, string $itemCode): ?InventoryItem
    {
        return InventoryItem::where('facility_id', $facilityId)
            ->where('item_code', $itemCode)
            ->first();
    }

    /**
     * Update an existing inventory item for current facility.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateInventoryItem(string $uuid, array $data): array
    {
        try {
            $this->validateFacilityId();
            
            $inventoryItem = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$inventoryItem) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found.',
                    'data' => []
                ];
            }

            // Process JSON fields
            $data = $this->processJsonFields($data);

            // Validate business rules before update
            $validationResult = $this->validateInventoryItemData($data, $uuid);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Prevent updating item code if it would cause a duplicate within same facility
            if (isset($data['item_code']) && $data['item_code'] !== $inventoryItem->item_code) {
                if ($this->repository->itemCodeExists($data['item_code'], $this->facilityId, $uuid)) {
                    return [
                        'success' => false,
                        'message' => 'Item code already exists in this facility. Please use a different code.',
                        'data' => []
                    ];
                }
            }

            // Prevent updating NDC code if it would cause a duplicate within same facility
            if (isset($data['ndc_code']) && $data['ndc_code'] !== $inventoryItem->ndc_code) {
                if ($this->repository->ndcCodeExists($data['ndc_code'], $this->facilityId, $uuid)) {
                    return [
                        'success' => false,
                        'message' => 'NDC code already exists in this facility. Please use a different code.',
                        'data' => []
                    ];
                }
            }

            DB::beginTransaction();

            $updated = $this->repository->update($inventoryItem, $data);

            if (!$updated) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to update inventory item.',
                    'data' => []
                ];
            }

            DB::commit();

            // Refresh the model to get updated attributes
            $inventoryItem->refresh();

            Log::info('Inventory item updated successfully', [
                'facility_id' => $this->facilityId,
                'item_uuid' => $uuid,
                'item_code' => $inventoryItem->item_code
            ]);

            return [
                'success' => true,
                'message' => 'Inventory item updated successfully.',
                'data' => $inventoryItem
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update inventory item', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update inventory item. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Delete an inventory item for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteInventoryItem(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            $inventoryItem = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$inventoryItem) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found.',
                    'data' => []
                ];
            }

            // Check if inventory item is currently in use (you would need to implement this check based on your business rules)
            // For example: if ($this->isInventoryItemInUse($inventoryItem)) { ... }

            $deleted = $this->repository->delete($inventoryItem);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete inventory item.',
                    'data' => []
                ];
            }

            Log::info('Inventory item deleted successfully', [
                'facility_id' => $this->facilityId,
                'item_uuid' => $uuid,
                'item_code' => $inventoryItem->item_code
            ]);

            return [
                'success' => true,
                'message' => 'Inventory item deleted successfully.',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete inventory item', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete inventory item. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Restore a soft-deleted inventory item for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function restoreInventoryItem(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            // Find including trashed, scoped by facility
            $inventoryItem = InventoryItem::withTrashed()
                ->where('item_uuid', $uuid)
                ->where('facility_id', $this->facilityId)
                ->first();

            if (!$inventoryItem) {
                return [
                    'success' => false,
                    'message' => 'Inventory item not found.',
                    'data' => []
                ];
            }

            if (!$inventoryItem->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Inventory item is not deleted.',
                    'data' => []
                ];
            }

            $restored = $this->repository->restore($inventoryItem);

            if (!$restored) {
                return [
                    'success' => false,
                    'message' => 'Failed to restore inventory item.',
                    'data' => []
                ];
            }

            $inventoryItem->refresh();

            Log::info('Inventory item restored successfully', [
                'facility_id' => $this->facilityId,
                'item_uuid' => $uuid,
                'item_code' => $inventoryItem->item_code
            ]);

            return [
                'success' => true,
                'message' => 'Inventory item restored successfully.',
                'data' => $inventoryItem
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restore inventory item', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to restore inventory item. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Get inventory items by category for current facility.
     *
     * @param string $category
     * @param array $filters
     * @return array
     */
    public function getInventoryItemsByCategory(string $category, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            $validCategories = [
                'medication', 'medical_supply', 'surgical_instrument', 'diagnostic_equipment',
                'implantable_device', 'prosthetic', 'laboratory_reagent',
                'personal_protective_equipment', 'administrative_supply', 'other'
            ];
            
            if (!in_array($category, $validCategories)) {
                return [
                    'success' => false,
                    'message' => 'Invalid category. Valid categories are: ' . implode(', ', $validCategories),
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            $filters['item_category'] = $category;

            $items = $this->repository->getByCategory($category, $filters);

            return [
                'success' => true,
                'message' => 'Inventory items retrieved successfully.',
                'data' => $items
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get inventory items by category', [
                'facility_id' => $this->facilityId,
                'category' => $category,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve inventory items. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get controlled substances for current facility.
     *
     * @param array $filters
     * @return array
     */
    public function getControlledSubstances(array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            $filters['is_controlled_substance'] = true;

            $items = $this->repository->getControlledSubstances($filters);

            return [
                'success' => true,
                'message' => 'Controlled substances retrieved successfully.',
                'data' => $items
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get controlled substances', [
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve controlled substances. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get special handling items for current facility.
     *
     * @param array $filters
     * @return array
     */
    public function getSpecialHandlingItems(array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;

            $items = $this->repository->getSpecialHandlingItems($filters);

            return [
                'success' => true,
                'message' => 'Special handling items retrieved successfully.',
                'data' => $items
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get special handling items', [
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve special handling items. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Search inventory items by name or code for current facility.
     *
     * @param string $searchTerm
     * @param array $filters
     * @return array
     */
    public function searchInventoryItems(string $searchTerm, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            if (strlen($searchTerm) < 2) {
                return [
                    'success' => false,
                    'message' => 'Search term must be at least 2 characters long.',
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;

            $items = $this->repository->search($searchTerm, $filters);

            return [
                'success' => true,
                'message' => 'Search completed successfully.',
                'data' => $items
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search inventory items', [
                'facility_id' => $this->facilityId,
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Search failed. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Validate inventory item data before creation/update.
     *
     * @param array $data
     * @param string|null $excludeUuid
     * @return array
     */
    public function validateInventoryItemData(array $data, ?string $excludeUuid = null): array
    {
        try {
            // Check required fields for creation
            $requiredFields = [
                'item_code' => 'Item code',
                'item_name' => 'Item name',
                'item_category' => 'Item category',
                'unit_of_measure' => 'Unit of measure',
                'package_quantity' => 'Package quantity',
                'currency_code' => 'Currency code',
                'status' => 'Status'
            ];

            foreach ($requiredFields as $field => $label) {
                if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                    return [
                        'success' => false,
                        'message' => "{$label} is required.",
                        'data' => []
                    ];
                }
            }

            // Validate item code uniqueness within facility
            if (isset($data['item_code'])) {
                $itemCode = trim((string) $data['item_code']);

                // Validate item code length early
                if ($itemCode === '') {
                    return [
                        'success' => false,
                        'message' => 'Item code cannot be empty.',
                        'data' => []
                    ];
                }

                if (strlen($itemCode) > 100) {
                    return [
                        'success' => false,
                        'message' => 'Item code must not exceed 100 characters.',
                        'data' => []
                    ];
                }

                $facilityId = (int) ($data['facility_id'] ?? $this->facilityId);

                $query = InventoryItem::query()
                    ->where('item_code', $itemCode)
                    ->where('facility_id', $facilityId);

                // If updating, exclude the current record
                if (!empty($excludeUuid)) {
                    $query->where('item_uuid', '!=', $excludeUuid);
                }

                if ($query->exists()) {
                    return [
                        'success' => false,
                        'message' => "Item code '{$itemCode}' already exists in this facility. Please choose a different code.",
                        'data' => [
                            'field' => 'item_code',
                            'code' => 'DUPLICATE_ITEM_CODE'
                        ]
                    ];
                }
            }

            // Validate NDC code uniqueness within facility if provided
            if (isset($data['ndc_code']) && !empty(trim($data['ndc_code']))) {
                $ndcCode = trim((string) $data['ndc_code']);

                if (strlen($ndcCode) > 20) {
                    return [
                        'success' => false,
                        'message' => 'NDC code must not exceed 20 characters.',
                        'data' => []
                    ];
                }

                $facilityId = (int) ($data['facility_id'] ?? $this->facilityId);

                $query = InventoryItem::query()
                    ->where('ndc_code', $ndcCode)
                    ->where('facility_id', $facilityId)
                    ->whereNotNull('ndc_code')
                    ->where('ndc_code', '!=', '');

                // If updating, exclude the current record
                if (!empty($excludeUuid)) {
                    $query->where('item_uuid', '!=', $excludeUuid);
                }

                if ($query->exists()) {
                    return [
                        'success' => false,
                        'message' => "NDC code '{$ndcCode}' already exists in this facility. Please use a different code.",
                        'data' => [
                            'field' => 'ndc_code',
                            'code' => 'DUPLICATE_NDC_CODE'
                        ]
                    ];
                }
            }

            // Validate item category
            if (isset($data['item_category'])) {
                $validCategories = [
                    'medication', 'medical_supply', 'surgical_instrument', 'diagnostic_equipment',
                    'implantable_device', 'prosthetic', 'laboratory_reagent',
                    'personal_protective_equipment', 'administrative_supply', 'other'
                ];
                
                if (!in_array($data['item_category'], $validCategories)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid item category. Valid categories are: ' . implode(', ', $validCategories),
                        'data' => []
                    ];
                }
            }

            // Validate controlled substance schedule
            if (isset($data['controlled_substance_schedule'])) {
                $validSchedules = ['I', 'II', 'III', 'IV', 'V', 'non_controlled'];
                
                if (!in_array($data['controlled_substance_schedule'], $validSchedules)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid controlled substance schedule. Valid schedules are: ' . implode(', ', $validSchedules),
                        'data' => []
                    ];
                }
            }

            // Validate status
            if (isset($data['status'])) {
                $validStatuses = ['active', 'inactive', 'discontinued', 'recalled'];
                
                if (!in_array($data['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                        'data' => []
                    ];
                }
            }

            // Validate currency code
            if (isset($data['currency_code'])) {
                if (strlen($data['currency_code']) !== 3 || !ctype_alpha($data['currency_code'])) {
                    return [
                        'success' => false,
                        'message' => 'Currency code must be exactly 3 alphabetic characters.',
                        'data' => []
                    ];
                }
            }

            // Validate numeric fields
            $numericFields = [
                'unit_cost' => ['min' => 0, 'max' => 99999999.99],
                'average_wholesale_price' => ['min' => 0, 'max' => 99999999.99],
                'package_quantity' => ['min' => 1, 'max' => 65535],
                'reorder_point' => ['min' => 0, 'max' => 65535],
                'reorder_quantity' => ['min' => 1, 'max' => 65535],
                'safety_stock_level' => ['min' => 0, 'max' => 65535],
                'max_stock_level' => ['min' => 0, 'max' => 65535],
            ];

            foreach ($numericFields as $field => $limits) {
                if (isset($data[$field]) && $data[$field] !== null) {
                    if (!is_numeric($data[$field])) {
                        return [
                            'success' => false,
                            'message' => "{$field} must be a valid number.",
                            'data' => []
                        ];
                    }
                    if ($data[$field] < $limits['min'] || $data[$field] > $limits['max']) {
                        return [
                            'success' => false,
                            'message' => "{$field} must be between {$limits['min']} and {$limits['max']}.",
                            'data' => []
                        ];
                    }
                }
            }

            // Validate JSON fields if provided
            $jsonFields = [
                'active_ingredients',
                'storage_requirements',
                'regulatory_approvals',
                'safety_warnings',
                'contraindications',
                'metadata'
            ];

            foreach ($jsonFields as $field) {
                if (isset($data[$field]) && !is_array($data[$field]) && !is_null($data[$field])) {
                    // Try to decode if it's a JSON string
                    if (is_string($data[$field])) {
                        json_decode($data[$field], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return [
                                'success' => false,
                                'message' => "Invalid JSON format for field: {$field}.",
                                'data' => []
                            ];
                        }
                    } else {
                        return [
                            'success' => false,
                            'message' => "Field {$field} must be a valid JSON array or object.",
                            'data' => []
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Validation successful.',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate inventory item data', [
                'facility_id' => $this->facilityId,
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Data validation failed. Please check your input.',
                'data' => []
            ];
        }
    }

    /**
     * Generate unique item code for facility.
     *
     * @param int $facilityId
     * @return string
     */
    private function generateUniqueItemCode(int $facilityId): string
    {
        do {
            // Generate random number between 1 and 9999
            $randomNum = random_int(1, 9999);
            
            // Format as 4-digit with leading zeros (e.g., 0001, 0042, 9999)
            $paddedNum = str_pad($randomNum, 4, '0', STR_PAD_LEFT);
            
            // Create code in format: INVT-XXXX
            $code = "INVT-{$paddedNum}";
            
            // Check if this code already exists for the facility
            $exists = $this->findByFacilityAndCode($facilityId, $code);
            
        } while ($exists);
        
        return $code;
    }

    /**
     * Process JSON fields for storage.
     *
     * @param array $data
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
            if (isset($data[$field])) {
                if (is_string($data[$field])) {
                    try {
                        $decoded = json_decode($data[$field], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $data[$field] = $decoded;
                        } else {
                            // If invalid JSON, set to empty array
                            $data[$field] = [];
                        }
                    } catch (\Exception $e) {
                        $data[$field] = [];
                    }
                } elseif (is_array($data[$field])) {
                    // Already an array, ensure it's valid
                    $data[$field] = $data[$field];
                } else {
                    // Set to empty array for any other type
                    $data[$field] = [];
                }
            }
        }

        return $data;
    }

    /**
     * Sanitize data for logging (remove sensitive information).
     *
     * @param array $data
     * @return array
     */
    private function sanitizeDataForLogging(array $data): array
    {
        $sensitiveFields = [
            'active_ingredients',
            'storage_requirements',
            'regulatory_approvals',
            'safety_warnings',
            'contraindications',
            'metadata',
            'special_handling_instructions'
        ];

        $sanitized = $data;
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }
}