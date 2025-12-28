<?php

namespace App\Services\Contracts;

use App\Models\InventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventoryItemServiceInterface
{
    /**
     * Get all inventory items with pagination.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return array
     */
    public function getAllInventoryItems(array $filters = [], int $perPage = 15): array;

    /**
     * Get inventory item by UUID.
     *
     * @param  string  $uuid
     * @return array
     */
    public function getInventoryItemByUuid(string $uuid): array;

    /**
     * Get inventory item by item code.
     *
     * @param  string  $itemCode
     * @return array
     */
    public function getInventoryItemByCode(string $itemCode): array;

    /**
     * Create a new inventory item.
     *
     * @param  array  $data
     * @return array
     */
    public function createInventoryItem(array $data): array;

    /**
     * Update an existing inventory item.
     *
     * @param  string  $uuid
     * @param  array  $data
     * @return array
     */
    public function updateInventoryItem(string $uuid, array $data): array;

    /**
     * Delete an inventory item.
     *
     * @param  string  $uuid
     * @return array
     */
    public function deleteInventoryItem(string $uuid): array;

    /**
     * Restore a deleted inventory item.
     *
     * @param  string  $uuid
     * @return array
     */
    public function restoreInventoryItem(string $uuid): array;

    /**
     * Get inventory items by category.
     *
     * @param  string  $category
     * @param  array  $filters
     * @return array
     */
    public function getInventoryItemsByCategory(string $category, array $filters = []): array;

    /**
     * Get controlled substances.
     *
     * @param  array  $filters
     * @return array
     */
    public function getControlledSubstances(array $filters = []): array;

    /**
     * Get items requiring special handling.
     *
     * @param  array  $filters
     * @return array
     */
    public function getSpecialHandlingItems(array $filters = []): array;

    /**
     * Search inventory items.
     *
     * @param  string  $searchTerm
     * @param  array  $filters
     * @param  int  $perPage
     * @return array
     */
    public function searchInventoryItems(string $searchTerm, array $filters = [], int $perPage = 15): array;

    /**
     * Validate item data before creation/update.
     *
     * @param  array  $data
     * @param  int|null  $excludeId
     * @return array
     */
    public function validateItemData(array $data, ?int $excludeId = null): array;
}