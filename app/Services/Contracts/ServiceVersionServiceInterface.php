<?php

namespace App\Services\Contracts;

use App\Models\ServiceVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface for ServiceVersion Service
 * 
 * Defines the contract for business logic operations on ServiceVersion entities.
 * Contains all business rules and validation logic.
 */
interface ServiceVersionServiceInterface
{
    /**
     * Get a service version by ID.
     *
     * @param int $id
     * @return array
     */
    public function getServiceVersion(int $id): array;

    /**
     * Get a service version by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getServiceVersionByUuid(string $uuid): array;

    /**
     * Get all service versions with optional filters.
     *
     * @param array $filters
     * @return array
     */
    public function getAllServiceVersions(array $filters = []): array;

    /**
     * Get paginated service versions.
     *
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function getPaginatedServiceVersions(int $perPage = 15, array $filters = []): array;

    /**
     * Create a new service version.
     *
     * @param array $data
     * @return array
     */
    public function createServiceVersion(array $data): array;

    /**
     * Update an existing service version.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateServiceVersion(int $id, array $data): array;

    /**
     * Delete a service version.
     *
     * @param int $id
     * @return array
     */
    public function deleteServiceVersion(int $id): array;

    /**
     * Get current version for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getCurrentVersion(int $serviceCatalogId, ?int $facilityId = null): array;

    /**
     * Set a version as current.
     *
     * @param int $versionId
     * @return array
     */
    public function setAsCurrentVersion(int $versionId): array;

    /**
     * Get versions valid on a specific date.
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getVersionsValidOnDate(string $date, ?int $serviceCatalogId = null, ?int $facilityId = null): array;

    /**
     * Validate version data before creation/update.
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    public function validateVersionData(array $data, ?int $excludeId = null): array;

    /**
     * Get price calculation for a service version.
     *
     * @param int $versionId
     * @return array
     */
    public function getPriceCalculation(int $versionId): array;

    /**
     * Get version history for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getVersionHistory(int $serviceCatalogId, ?int $facilityId = null): array;

    /**
     * Check if a version is billable under specific conditions.
     *
     * @param int $versionId
     * @param array $conditions
     * @return array
     */
    public function checkBillability(int $versionId, array $conditions = []): array;

    /**
     * Calculate insurance coverage for a version.
     *
     * @param int $versionId
     * @param string $insuranceType
     * @return array
     */
    public function calculateInsuranceCoverage(int $versionId, string $insuranceType): array;
}