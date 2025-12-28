<?php

namespace App\Repositories\Contracts;

use App\Models\InventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryItemRepositoryInterface
{
    /**
     * Find inventory item by ID.
     *
     * @param  int  $id
     * @return InventoryItem|null
     */
    public function findById(int $id): ?InventoryItem;

    /**
     * Find inventory item by UUID.
     *
     * @param  string  $uuid
     * @return InventoryItem|null
     */
    public function findByUuid(string $uuid): ?InventoryItem;

    /**
     * Find inventory item by item code.
     *
     * @param  string  $itemCode
     * @return InventoryItem|null
     */
    public function findByItemCode(string $itemCode): ?InventoryItem;

    /**
     * Get all inventory items with pagination.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @param  array  $columns
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Get all inventory items by category.
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
     * Permanently delete an inventory item.
     *
     * @param  InventoryItem  $inventoryItem
     * @return bool
     */
    public function forceDelete(InventoryItem $inventoryItem): bool;

    /**
     * Search inventory items by various criteria.
     *
     * @param  string  $searchTerm
     * @param  array  $filters
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function search(string $searchTerm, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Check if item code exists.
     *
     * @param  string  $itemCode
     * @param  int|null  $excludeId
     * @return bool
     */
    public function itemCodeExists(string $itemCode, ?int $excludeId = null): bool;

    /**
     * Check if NDC code exists.
     *
     * @param  string  $ndcCode
     * @param  int|null  $excludeId
     * @return bool
     */
    public function ndcCodeExists(string $ndcCode, ?int $excludeId = null): bool;
}