<?php

namespace App\Services\Contracts;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

interface DepartmentServiceInterface
{
    /**
     * Get all departments with pagination.
     *
     * @param array $filters
     * @return array
     */
    public function getAllDepartments(array $filters = []): array;

    /**
     * Get department by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getDepartmentByUuid(string $uuid): array;

    /**
     * Create a new department.
     *
     * @param array $data
     * @return array
     */
    public function createDepartment(array $data): array;

    /**
     * Update an existing department.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateDepartment(string $uuid, array $data): array;

    /**
     * Delete a department.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteDepartment(string $uuid): array;

    /**
     * Restore a soft-deleted department.
     *
     * @param string $uuid
     * @return array
     */
    public function restoreDepartment(string $uuid): array;

    /**
     * Get departments by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getDepartmentsByFacility(int $facilityId, array $filters = []): array;

    /**
     * Get departments by type.
     *
     * @param string $type
     * @param array $filters
     * @return array
     */
    public function getDepartmentsByType(string $type, array $filters = []): array;

    /**
     * Validate department data before creation/update.
     *
     * @param array $data
     * @param int|null $departmentId
     * @return array
     */
    public function validateDepartmentData(array $data, ?int $departmentId = null): array;
}