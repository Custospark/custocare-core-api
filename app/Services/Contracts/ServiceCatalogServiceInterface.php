<?php

namespace App\Services\Contracts;

use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface for ServiceCatalog business logic operations.
 * Defines the contract for business operations on ServiceCatalog entities.
 */
interface ServiceCatalogServiceInterface
{
    /**
     * Get all service catalogs with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllServiceCatalogs(array $filters = [], int $perPage = 15): array;

    /**
     * Get a service catalog by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getServiceCatalogByUuid(string $uuid): array;

    /**
     * Get a service catalog by service code.
     *
     * @param string $serviceCode
     * @return array
     */
    public function getServiceCatalogByCode(string $serviceCode): array;

    /**
     * Create a new service catalog.
     *
     * @param array $data
     * @return array
     */
    public function createServiceCatalog(array $data): array;

    /**
     * Update an existing service catalog.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateServiceCatalog(string $uuid, array $data): array;

    /**
     * Delete a service catalog.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteServiceCatalog(string $uuid): array;

    /**
     * Restore a soft-deleted service catalog.
     *
     * @param string $uuid
     * @return array
     */
    public function restoreServiceCatalog(string $uuid): array;

    /**
     * Get active service catalogs effective on a specific date.
     *
     * @param string $date
     * @param array $filters
     * @return array
     */
    public function getEffectiveServices(string $date, array $filters = []): array;

    /**
     * Get service catalogs by code system.
     *
     * @param string $codeSystem
     * @param array $filters
     * @return array
     */
    public function getByCodeSystem(string $codeSystem, array $filters = []): array;

    /**
     * Get service catalogs by category.
     *
     * @param string $category
     * @param array $filters
     * @return array
     */
    public function getByCategory(string $category, array $filters = []): array;

    /**
     * Search service catalogs by name or code.
     *
     * @param string $searchTerm
     * @param array $filters
     * @return array
     */
    public function searchServiceCatalogs(string $searchTerm, array $filters = []): array;

    /**
     * Validate service catalog data before creation/update.
     *
     * @param array $data
     * @param string|null $excludeUuid
     * @return array
     */
    public function validateServiceCatalogData(array $data, ?string $excludeUuid = null): array;

    /**
     * Check if a service is currently effective.
     *
     * @param string $uuid
     * @param string|null $date
     * @return array
     */
    public function checkServiceEffectiveness(string $uuid, ?string $date = null): array;
}