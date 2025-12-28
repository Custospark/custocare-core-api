<?php

namespace App\Repositories\Contracts;

use App\Models\ServiceVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface for ServiceVersion Repository
 * 
 * Defines the contract for data access operations on ServiceVersion entities.
 * Follows Repository Pattern to abstract database operations.
 */
interface ServiceVersionRepositoryInterface
{
    /**
     * Find a service version by ID.
     *
     * @param int $id
     * @return ServiceVersion|null
     */
    public function findById(int $id): ?ServiceVersion;

    /**
     * Find a service version by UUID.
     *
     * @param string $uuid
     * @return ServiceVersion|null
     */
    public function findByUuid(string $uuid): ?ServiceVersion;

    /**
     * Get all service versions.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get paginated service versions.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get current versions by service catalog.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getCurrentVersions(int $serviceCatalogId, ?int $facilityId = null): Collection;

    /**
     * Get versions valid on a specific date.
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getValidOnDate(string $date, ?int $serviceCatalogId = null, ?int $facilityId = null): Collection;

    /**
     * Create a new service version.
     *
     * @param array $data
     * @return ServiceVersion
     */
    public function create(array $data): ServiceVersion;

    /**
     * Update an existing service version.
     *
     * @param ServiceVersion $serviceVersion
     * @param array $data
     * @return ServiceVersion
     */
    public function update(ServiceVersion $serviceVersion, array $data): ServiceVersion;

    /**
     * Delete a service version.
     *
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function delete(ServiceVersion $serviceVersion): bool;

    /**
     * Update the current version flag for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @param int $newCurrentVersionId
     * @return bool
     */
    public function updateCurrentVersion(int $serviceCatalogId, ?int $facilityId, int $newCurrentVersionId): bool;

    /**
     * Check if a version number already exists for service catalog and facility.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @param string $versionNumber
     * @param int|null $excludeId
     * @return bool
     */
    public function versionNumberExists(int $serviceCatalogId, ?int $facilityId, string $versionNumber, ?int $excludeId = null): bool;

    /**
     * Get version history for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getVersionHistory(int $serviceCatalogId, ?int $facilityId = null): Collection;

    /**
     * Calculate and update final price based on markup.
     *
     * @param ServiceVersion $serviceVersion
     * @return ServiceVersion
     */
    public function recalculateFinalPrice(ServiceVersion $serviceVersion): ServiceVersion;
}