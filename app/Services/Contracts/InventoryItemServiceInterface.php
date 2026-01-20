<?php

namespace App\Services\Contracts;

interface InventoryItemServiceInterface
{
    /**
     * Get all inventory items for current facility with pagination.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return array
     */
    public function getAllInventoryItems(array $filters = [], int $perPage = 15): array;

    /**
     * Get an inventory item by UUID for current facility.
     *
     * @param  string  $uuid
     * @return array
     */
    public function getInventoryItemByUuid(string $uuid): array;

    /**
     * Get an inventory item by item code for current facility.
     *
     * @param  string  $itemCode
     * @return array
     */
    public function getInventoryItemByCode(string $itemCode): array;

    /**
     * Create a new inventory item for current facility.
     *
     * @param  array  $data
     * @return array
     */
    public function createInventoryItem(array $data): array;

    /**
     * Update an existing inventory item for current facility.
     *
     * @param  string  $uuid
     * @param  array  $data
     * @return array
     */
    public function updateInventoryItem(string $uuid, array $data): array;

    /**
     * Delete an inventory item for current facility.
     *
     * @param  string  $uuid
     * @return array
     */
    public function deleteInventoryItem(string $uuid): array;

    /**
     * Restore a soft-deleted inventory item for current facility.
     *
     * @param  string  $uuid
     * @return array
     */
    public function restoreInventoryItem(string $uuid): array;

    /**
     * Get inventory items by category for current facility.
     *
     * @param  string  $category
     * @param  array  $filters
     * @return array
     */
    public function getInventoryItemsByCategory(string $category, array $filters = []): array;

    /**
     * Get controlled substances for current facility.
     *
     * @param  array  $filters
     * @return array
     */
    public function getControlledSubstances(array $filters = []): array;

    /**
     * Get items requiring special handling for current facility.
     *
     * @param  array  $filters
     * @return array
     */
    public function getSpecialHandlingItems(array $filters = []): array;

    /**
     * Search inventory items by name or code for current facility.
     *
     * @param  string  $searchTerm
     * @param  array  $filters
     * @return array
     */
    public function searchInventoryItems(string $searchTerm, array $filters = []): array;

    /**
     * Validate inventory item data before creation/update.
     *
     * @param  array  $data
     * @param  string|null  $excludeUuid
     * @return array
     */
    public function validateInventoryItemData(array $data, ?string $excludeUuid = null): array;
}