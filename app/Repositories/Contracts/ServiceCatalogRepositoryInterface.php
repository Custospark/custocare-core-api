<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface for ServiceCatalog repository operations.
 * Defines the contract for database interactions with ServiceCatalog entities.
 */
interface ServiceCatalogRepositoryInterface
{
    /**
     * Find a service catalog by its UUID.
     *
     * @param string $uuid
     * @return ServiceCatalog|null
     */
    public function findByUuid(string $uuid): ?ServiceCatalog;

    /**
     * Find a service catalog by its service code.
     *
     * @param string $serviceCode
     * @return ServiceCatalog|null
     */
    public function findByServiceCode(string $serviceCode): ?ServiceCatalog;

    /**
     * Get all service catalogs with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get all service catalogs.
     *
     * @param array $filters
     * @return Collection
     */
    public function all(array $filters = []): Collection;

    /**
     * Create a new service catalog.
     *
     * @param array $data
     * @return ServiceCatalog
     */
    public function create(array $data): ServiceCatalog;

    /**
     * Update an existing service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @param array $data
     * @return bool
     */
    public function update(ServiceCatalog $serviceCatalog, array $data): bool;

    /**
     * Delete a service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool|null
     */
    public function delete(ServiceCatalog $serviceCatalog): ?bool;

    /**
     * Restore a soft-deleted service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool
     */
    public function restore(ServiceCatalog $serviceCatalog): bool;

    /**
     * Force delete a service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool|null
     */
    public function forceDelete(ServiceCatalog $serviceCatalog): ?bool;

    /**
     * Get active service catalogs effective on a specific date.
     *
     * @param string $date
     * @param array $filters
     * @return Collection
     */
    public function getEffectiveServices(string $date, array $filters = []): Collection;

    /**
     * Get service catalogs by code system.
     *
     * @param string $codeSystem
     * @param array $filters
     * @return Collection
     */
    public function getByCodeSystem(string $codeSystem, array $filters = []): Collection;

    /**
     * Get service catalogs by category.
     *
     * @param string $category
     * @param array $filters
     * @return Collection
     */
    public function getByCategory(string $category, array $filters = []): Collection;

    /**
     * Search service catalogs by name or code.
     *
     * @param string $searchTerm
     * @param array $filters
     * @return Collection
     */
    public function search(string $searchTerm, array $filters = []): Collection;

    /**
     * Check if a service code already exists.
     *
     * @param string $serviceCode
     * @param string|null $excludeUuid
     * @return bool
     */
    public function serviceCodeExists(string $serviceCode, ?string $excludeUuid = null): bool;
}