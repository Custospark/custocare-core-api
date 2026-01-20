<?php

namespace App\Repositories\InventoryItem;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryItemRepository implements InventoryItemRepositoryInterface
{
    /**
     * The InventoryItem model instance.
     *
     * @var InventoryItem
     */
    protected InventoryItem $model;

    /**
     * Create a new repository instance.
     *
     * @param InventoryItem $model
     */
    public function __construct(InventoryItem $model)
    {
        $this->model = $model;
    }

    /**
     * Get all inventory items with pagination.
     *
     * @param  int  $perPage
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error getting paginated inventory items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty paginator with correct structure
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Find inventory item by UUID for specific facility.
     *
     * @param  string  $uuid
     * @param  int  $facilityId
     * @return InventoryItem|null
     */
    public function findByUuidAndFacility(string $uuid, int $facilityId): ?InventoryItem
    {
        try {
            return $this->model->with(['createdBy'])
                ->where('item_uuid', $uuid)
                ->where('facility_id', $facilityId)
                ->first();
        } catch (\Exception $e) {
            Log::error('Error finding inventory item by UUID and facility', [
                'uuid' => $uuid,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find inventory item by item code for specific facility.
     *
     * @param  string  $itemCode
     * @param  int  $facilityId
     * @return InventoryItem|null
     */
    public function findByItemCodeAndFacility(string $itemCode, int $facilityId): ?InventoryItem
    {
        try {
            return $this->model->with(['createdBy'])
                ->where('item_code', $itemCode)
                ->where('facility_id', $facilityId)
                ->first();
        } catch (\Exception $e) {
            Log::error('Error finding inventory item by item code and facility', [
                'item_code' => $itemCode,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get inventory items by category.
     *
     * @param  string  $category
     * @param  array  $filters
     * @return Collection
     */
    public function getByCategory(string $category, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters first (includes facility_id)
            $this->applyFilters($query, $filters);

            // Then apply category filter
            $query->where('item_category', $category);

            return $query->orderBy('item_name')->get();
        } catch (\Exception $e) {
            Log::error('Error getting inventory items by category', [
                'category' => $category,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Get controlled substances.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getControlledSubstances(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters first (includes facility_id)
            $this->applyFilters($query, $filters);

            // Then apply controlled substance filters
            if (isset($filters['is_controlled_substance']) && $filters['is_controlled_substance']) {
                $query->whereNotNull('controlled_substance_schedule')
                      ->where('controlled_substance_schedule', '!=', 'non_controlled');
            } else {
                // Default: get all controlled substances
                $query->whereNotNull('controlled_substance_schedule')
                      ->where('controlled_substance_schedule', '!=', 'non_controlled');
            }

            // Apply schedule filter if specified
            if (isset($filters['controlled_substance_schedule'])) {
                $query->where('controlled_substance_schedule', $filters['controlled_substance_schedule']);
            }

            return $query->orderBy('controlled_substance_schedule')->get();
        } catch (\Exception $e) {
            Log::error('Error getting controlled substances', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Get items requiring special handling.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getSpecialHandlingItems(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters first (includes facility_id)
            $this->applyFilters($query, $filters);

            // Then apply special handling conditions
            $query->where(function ($q) {
                $q->where('is_hazardous', true)
                  ->orWhere('requires_refrigeration', true)
                  ->orWhere('requires_controlled_access', true)
                  ->orWhereNotNull('special_handling_instructions');
            });

            return $query->orderBy('item_name')->get();
        } catch (\Exception $e) {
            Log::error('Error getting special handling items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Create a new inventory item.
     *
     * @param  array  $data
     * @return InventoryItem
     */
    public function create(array $data): InventoryItem
    {
        DB::beginTransaction();
        
        try {
            // Generate UUID if not provided
            if (!isset($data['item_uuid'])) {
                $data['item_uuid'] = \Illuminate\Support\Str::uuid()->toString();
            }

            $inventoryItem = $this->model->create($data);
            
            DB::commit();
            
            // Reload with relationships
            return $inventoryItem->load('createdBy');
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating inventory item', [
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing inventory item.
     *
     * @param  InventoryItem  $inventoryItem
     * @param  array  $data
     * @return bool
     */
    public function update(InventoryItem $inventoryItem, array $data): bool
    {
        DB::beginTransaction();
        
        try {
            $result = $inventoryItem->update($data);
            
            DB::commit();
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating inventory item', [
                'item_uuid' => $inventoryItem->item_uuid,
                'data' => $this->sanitizeDataForLogging($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete an inventory item (soft delete).
     *
     * @param  InventoryItem  $inventoryItem
     * @return bool|null
     */
    public function delete(InventoryItem $inventoryItem): ?bool
    {
        try {
            return $inventoryItem->delete();
        } catch (\Exception $e) {
            Log::error('Error soft deleting inventory item', [
                'item_uuid' => $inventoryItem->item_uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted inventory item.
     *
     * @param  InventoryItem  $inventoryItem
     * @return bool
     */
    public function restore(InventoryItem $inventoryItem): bool
    {
        try {
            return $inventoryItem->restore();
        } catch (\Exception $e) {
            Log::error('Error restoring inventory item', [
                'item_uuid' => $inventoryItem->item_uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Search inventory items.
     *
     * @param  string  $searchTerm
     * @param  array  $filters
     * @return Collection
     */
    public function search(string $searchTerm, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters first (includes facility_id)
            $this->applyFilters($query, $filters);

            // Then apply search conditions
            $query->where(function ($q) use ($searchTerm) {
                $q->where('item_code', 'like', "%{$searchTerm}%")
                  ->orWhere('item_name', 'like', "%{$searchTerm}%")
                  ->orWhere('generic_name', 'like', "%{$searchTerm}%")
                  ->orWhere('brand_name', 'like', "%{$searchTerm}%")
                  ->orWhere('ndc_code', 'like', "%{$searchTerm}%")
                  ->orWhere('manufacturer', 'like', "%{$searchTerm}%")
                  ->orWhere('manufacturer_item_number', 'like', "%{$searchTerm}%");
            });

            return $query->orderBy('item_name')->get();
        } catch (\Exception $e) {
            Log::error('Error searching inventory items', [
                'search_term' => $searchTerm,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Check if item code exists within a facility.
     *
     * @param  string  $itemCode
     * @param  int  $facilityId
     * @param  string|null  $excludeUuid
     * @return bool
     */
    public function itemCodeExists(string $itemCode, int $facilityId, ?string $excludeUuid = null): bool
    {
        try {
            $query = $this->model->where('item_code', $itemCode)
                ->where('facility_id', $facilityId);
            
            if ($excludeUuid) {
                $query->where('item_uuid', '!=', $excludeUuid);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Error checking if item code exists', [
                'item_code' => $itemCode,
                'facility_id' => $facilityId,
                'exclude_uuid' => $excludeUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check if NDC code exists within a facility.
     *
     * @param  string  $ndcCode
     * @param  int  $facilityId
     * @param  string|null  $excludeUuid
     * @return bool
     */
    public function ndcCodeExists(string $ndcCode, int $facilityId, ?string $excludeUuid = null): bool
    {
        try {
            $query = $this->model->where('ndc_code', $ndcCode)
                ->where('facility_id', $facilityId)
                ->whereNotNull('ndc_code');
            
            if ($excludeUuid) {
                $query->where('item_uuid', '!=', $excludeUuid);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Error checking if NDC code exists', [
                'ndc_code' => $ndcCode,
                'facility_id' => $facilityId,
                'exclude_uuid' => $excludeUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Apply filters to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
     * @return void
     */
    protected function applyFilters($query, array $filters): void
    {
        // Facility ID filter (MANDATORY for all queries)
        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        // Status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Item category filter
        if (isset($filters['item_category'])) {
            $query->where('item_category', $filters['item_category']);
        }

        // Is controlled substance filter
        if (isset($filters['is_controlled_substance'])) {
            if ($filters['is_controlled_substance']) {
                $query->whereNotNull('controlled_substance_schedule')
                      ->where('controlled_substance_schedule', '!=', 'non_controlled');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('controlled_substance_schedule')
                      ->orWhere('controlled_substance_schedule', 'non_controlled');
                });
            }
        }

        // Controlled substance schedule filter
        if (isset($filters['controlled_substance_schedule'])) {
            $query->where('controlled_substance_schedule', $filters['controlled_substance_schedule']);
        }

        // Requires prescription filter
        if (isset($filters['requires_prescription'])) {
            $query->where('requires_prescription', (bool) $filters['requires_prescription']);
        }

        // Is hazardous filter
        if (isset($filters['is_hazardous'])) {
            $query->where('is_hazardous', (bool) $filters['is_hazardous']);
        }

        // Requires refrigeration filter
        if (isset($filters['requires_refrigeration'])) {
            $query->where('requires_refrigeration', (bool) $filters['requires_refrigeration']);
        }

        // Requires controlled access filter
        if (isset($filters['requires_controlled_access'])) {
            $query->where('requires_controlled_access', (bool) $filters['requires_controlled_access']);
        }

        // Is billable filter
        if (isset($filters['is_billable'])) {
            $query->where('is_billable', (bool) $filters['is_billable']);
        }

        // Date range filters
        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }
        
        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }
    }

    /**
     * Sanitize data for logging (remove sensitive information).
     *
     * @param  array  $data
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