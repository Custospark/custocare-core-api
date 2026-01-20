<?php

namespace App\Repositories\Contracts;

use App\Models\InventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryItemRepositoryInterface
{
    /**
     * Get all inventory items with pagination.
     *
     * @param  int  $perPage
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Find inventory item by UUID for specific facility.
     *
     * @param  string  $uuid
     * @param  int  $facilityId
     * @return InventoryItem|null
     */
    public function findByUuidAndFacility(string $uuid, int $facilityId): ?InventoryItem;

    /**
     * Find inventory item by item code for specific facility.
     *
     * @param  string  $itemCode
     * @param  int  $facilityId
     * @return InventoryItem|null
     */
    public function findByItemCodeAndFacility(string $itemCode, int $facilityId): ?InventoryItem;

    /**
     * Get inventory items by category.
     *
     * @param  string  $category
     * @param  array  $filters
     * @return Collection
     */
    public function getByCategory(string $category, array $filters = []): Collection;

    /**
     * Get controlled substances.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getControlledSubstances(array $filters = []): Collection;

    /**
     * Get items requiring special handling.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getSpecialHandlingItems(array $filters = []): Collection;

    /**
     * Create a new inventory item.
     *
     * @param  array  $data
     * @return InventoryItem
     */
    public function create(array $data): InventoryItem;

    /**
     * Update an existing inventory item.
     *
     * @param  InventoryItem  $inventoryItem
     * @param  array  $data
     * @return bool
     */
    public function update(InventoryItem $inventoryItem, array $data): bool;

    /**
     * Delete an inventory item (soft delete).
     *
     * @param  InventoryItem  $inventoryItem
     * @return bool|null
     */
    public function delete(InventoryItem $inventoryItem): ?bool;

    /**
     * Restore a soft-deleted inventory item.
     *
     * @param  InventoryItem  $inventoryItem
     * @return bool
     */
    public function restore(InventoryItem $inventoryItem): bool;

    /**
     * Search inventory items.
     *
     * @param  string  $searchTerm
     * @param  array  $filters
     * @return Collection
     */
    public function search(string $searchTerm, array $filters = []): Collection;

    /**
     * Check if item code exists within a facility.
     *
     * @param  string  $itemCode
     * @param  int  $facilityId
     * @param  string|null  $excludeUuid
     * @return bool
     */
    public function itemCodeExists(string $itemCode, int $facilityId, ?string $excludeUuid = null): bool;

    /**
     * Check if NDC code exists within a facility.
     *
     * @param  string  $ndcCode
     * @param  int  $facilityId
     * @param  string|null  $excludeUuid
     * @return bool
     */
    public function ndcCodeExists(string $ndcCode, int $facilityId, ?string $excludeUuid = null): bool;
}