<?php

namespace App\Repositories\Contracts;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface FacilityRepositoryInterface
 * 
 * Contract for Facility data access layer.
 * Defines all database operations for Facility entity.
 */
interface FacilityRepositoryInterface
{
    /**
     * Get all facilities with optional filters.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function getAll(array $filters = [], array $relations = []): Collection;

    /**
     * Get paginated facilities with optional filters.
     *
     * @param int $perPage
     * @param array $filters
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator;

    /**
     * Find a facility by ID.
     *
     * @param int $id
     * @param array $relations
     * @return Facility|null
     */
    public function findById(int $id, array $relations = []): ?Facility;

    /**
     * Find a facility by UUID.
     *
     * @param string $uuid
     * @param array $relations
     * @return Facility|null
     */
    public function findByUuid(string $uuid, array $relations = []): ?Facility;

    /**
     * Find a facility by facility code.
     *
     * @param string $facilityCode
     * @param array $relations
     * @return Facility|null
     */
    public function findByCode(string $facilityCode, array $relations = []): ?Facility;

    /**
     * Create a new facility.
     *
     * @param array $data
     * @return Facility
     */
    public function create(array $data): Facility;

    /**
     * Update an existing facility.
     *
     * @param int $id
     * @param array $data
     * @return Facility
     */
    public function update(int $id, array $data): Facility;

    /**
     * Delete a facility (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Force delete a facility.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Restore a soft-deleted facility.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Get facilities by location.
     *
     * @param string $countryCode
     * @param string|null $stateProvince
     * @param string|null $city
     * @return Collection
     */
    public function getByLocation(string $countryCode, ?string $stateProvince = null, ?string $city = null): Collection;

    /**
     * Get facilities by type and status.
     *
     * @param string $type
     * @param string $status
     * @return Collection
     */
    public function getByTypeAndStatus(string $type, string $status): Collection;

    /**
     * Search facilities by name or code.
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function search(string $query, int $limit = 10): Collection;

    /**
     * Check if facility code already exists.
     *
     * @param string $facilityCode
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists(string $facilityCode, ?int $excludeId = null): bool;

    /**
     * Get facilities with emergency departments.
     *
     * @param array $filters
     * @return Collection
     */
    public function getWithEmergencyDepartments(array $filters = []): Collection;
}