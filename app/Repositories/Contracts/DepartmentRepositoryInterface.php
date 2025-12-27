<?php

namespace App\Repositories\Contracts;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DepartmentRepositoryInterface
{
    /**
     * Find a department by its UUID.
     *
     * @param string $uuid
     * @return Department|null
     */
    public function findByUuid(string $uuid): ?Department;

    /**
     * Find a department by ID.
     *
     * @param int $id
     * @return Department|null
     */
    public function findById(int $id): ?Department;

    /**
     * Get all departments with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get departments by facility ID.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacilityId(int $facilityId, array $filters = []): Collection;

    /**
     * Get departments by type.
     *
     * @param string $type
     * @param array $filters
     * @return Collection
     */
    public function getByType(string $type, array $filters = []): Collection;

    /**
     * Create a new department.
     *
     * @param array $data
     * @return Department
     */
    public function create(array $data): Department;

    /**
     * Update an existing department.
     *
     * @param Department $department
     * @param array $data
     * @return bool
     */
    public function update(Department $department, array $data): bool;

    /**
     * Delete a department.
     *
     * @param Department $department
     * @return bool|null
     */
    public function delete(Department $department): ?bool;

    /**
     * Restore a soft-deleted department.
     *
     * @param Department $department
     * @return bool
     */
    public function restore(Department $department): bool;

    /**
     * Permanently delete a department.
     *
     * @param Department $department
     * @return bool
     */
    public function forceDelete(Department $department): bool;

    /**
     * Check if department code is unique within facility.
     *
     * @param string $departmentCode
     * @param int $facilityId
     * @param int|null $excludeId
     * @return bool
     */
    public function isDepartmentCodeUnique(string $departmentCode, int $facilityId, ?int $excludeId = null): bool;
}