<?php

namespace App\Repositories\InventoryItem;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryItemRepository implements InventoryItemRepositoryInterface
{
    /**
     * @var InventoryItem
     */
    protected $model;

    /**
     * InventoryItemRepository constructor.
     *
     * @param InventoryItem $model
     */
    public function __construct(InventoryItem $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?InventoryItem
    {
        try {
            return $this->model->with(['createdBy'])->find($id);
        } catch (\Exception $e) {
            Log::error('Error finding inventory item by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?InventoryItem
    {
        try {
            return $this->model->with(['createdBy'])->where('item_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Error finding inventory item by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByItemCode(string $itemCode): ?InventoryItem
    {
        try {
            return $this->model->with(['createdBy'])->where('item_code', $itemCode)->first();
        } catch (\Exception $e) {
            Log::error('Error finding inventory item by item code', [
                'item_code' => $itemCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['createdBy']);

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('created_at', 'desc')->paginate($perPage, $columns);
        } catch (\Exception $e) {
            Log::error('Error getting paginated inventory items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty paginator instead of throwing
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getByCategory(string $category, array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy'])->where('item_category', $category);

            // Apply additional filters
            $this->applyFilters($query, $filters);

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
     * {@inheritdoc}
     */
    public function getControlledSubstances(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy'])
                ->whereNotNull('controlled_substance_schedule')
                ->where('controlled_substance_schedule', '!=', 'non_controlled');

            $this->applyFilters($query, $filters);

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
     * {@inheritdoc}
     */
    public function getSpecialHandlingItems(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['createdBy'])
                ->where(function ($q) {
                    $q->where('is_hazardous', true)
                      ->orWhere('requires_refrigeration', true)
                      ->orWhere('requires_controlled_access', true)
                      ->orWhereNotNull('special_handling_instructions');
                });

            $this->applyFilters($query, $filters);

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
     * {@inheritdoc}
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
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
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
                'inventory_item_id' => $inventoryItem->id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(InventoryItem $inventoryItem): ?bool
    {
        try {
            return $inventoryItem->delete();
        } catch (\Exception $e) {
            Log::error('Error soft deleting inventory item', [
                'inventory_item_id' => $inventoryItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restore(InventoryItem $inventoryItem): bool
    {
        try {
            return $inventoryItem->restore();
        } catch (\Exception $e) {
            Log::error('Error restoring inventory item', [
                'inventory_item_id' => $inventoryItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(InventoryItem $inventoryItem): bool
    {
        try {
            return $inventoryItem->forceDelete();
        } catch (\Exception $e) {
            Log::error('Error force deleting inventory item', [
                'inventory_item_id' => $inventoryItem->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $searchTerm, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['createdBy'])
                ->where(function ($q) use ($searchTerm) {
                    $q->where('item_code', 'like', "%{$searchTerm}%")
                      ->orWhere('item_name', 'like', "%{$searchTerm}%")
                      ->orWhere('generic_name', 'like', "%{$searchTerm}%")
                      ->orWhere('brand_name', 'like', "%{$searchTerm}%")
                      ->orWhere('ndc_code', 'like', "%{$searchTerm}%")
                      ->orWhere('manufacturer', 'like', "%{$searchTerm}%")
                      ->orWhere('manufacturer_item_number', 'like', "%{$searchTerm}%");
                });

            $this->applyFilters($query, $filters);

            return $query->orderBy('item_name')->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error searching inventory items', [
                'search_term' => $searchTerm,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function itemCodeExists(string $itemCode, ?int $excludeId = null): bool
    {
        try {
            $query = $this->model->where('item_code', $itemCode);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Error checking if item code exists', [
                'item_code' => $itemCode,
                'exclude_id' => $excludeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ndcCodeExists(string $ndcCode, ?int $excludeId = null): bool
    {
        try {
            $query = $this->model->where('ndc_code', $ndcCode);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Error checking if NDC code exists', [
                'ndc_code' => $ndcCode,
                'exclude_id' => $excludeId,
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
        // Status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Category filter
        if (isset($filters['category'])) {
            $query->where('item_category', $filters['category']);
        }

        // Controlled substance filter
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

        // Requires prescription filter
        if (isset($filters['requires_prescription'])) {
            $query->where('requires_prescription', $filters['requires_prescription']);
        }

        // Is hazardous filter
        if (isset($filters['is_hazardous'])) {
            $query->where('is_hazardous', $filters['is_hazardous']);
        }

        // Requires refrigeration filter
        if (isset($filters['requires_refrigeration'])) {
            $query->where('requires_refrigeration', $filters['requires_refrigeration']);
        }

        // Date range filters
        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }
        
        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }
    }
}