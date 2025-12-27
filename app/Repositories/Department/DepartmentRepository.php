<?php

namespace App\Repositories\Department;

use App\Models\Department;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class DepartmentRepository implements DepartmentRepositoryInterface
{
    /**
     * Find a department by its UUID.
     *
     * @param string $uuid
     * @return Department|null
     */
    public function findByUuid(string $uuid): ?Department
    {
        try {
            return Department::where('department_uuid', $uuid)->first();
        } catch (\Exception $e) {
            // Log the exception for debugging
           Log::error('Failed to find department by UUID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a department by ID.
     *
     * @param int $id
     * @return Department|null
     */
    public function findById(int $id): ?Department
    {
        try {
            return Department::find($id);
        } catch (\Exception $e) {
           Log::error('Failed to find department by ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all departments with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = Department::query();

            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['department_type'])) {
                $query->where('department_type', $filters['department_type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('department_code', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('department_name', 'like', '%' . $filters['search'] . '%');
                });
            }

            // Eager load relationships
            $query->with(['facility', 'parentDepartment', 'departmentHead']);

            // Apply sorting
            $sortField = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $query->orderBy($sortField, $sortOrder);

            return $query->paginate($perPage);
        } catch (\Exception $e) {
           Log::error('Failed to get paginated departments: ' . $e->getMessage());
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get departments by facility ID.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacilityId(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = Department::where('facility_id', $facilityId);

            if (!empty($filters['department_type'])) {
                $query->where('department_type', $filters['department_type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['with_children'])) {
                $query->with('childDepartments');
            }

            return $query->get();
        } catch (\Exception $e) {
           Log::error('Failed to get departments by facility ID: ' . $e->getMessage());
            return new Collection();
        }
    }

    /**
     * Get departments by type.
     *
     * @param string $type
     * @param array $filters
     * @return Collection
     */
    public function getByType(string $type, array $filters = []): Collection
    {
        try {
            $query = Department::where('department_type', $type);

            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return $query->get();
        } catch (\Exception $e) {
           Log::error('Failed to get departments by type: ' . $e->getMessage());
            return new Collection();
        }
    }

    /**
     * Create a new department.
     *
     * @param array $data
     * @return Department
     */
    public function create(array $data): Department
    {
        try {
            // Generate UUID if not provided
            if (!isset($data['department_uuid'])) {
                $data['department_uuid'] = \Illuminate\Support\Str::uuid()->toString();
            }

            return Department::create($data);
        } catch (\Exception $e) {
           Log::error('Failed to create department: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create department. Please try again.');
        }
    }

    /**
     * Update an existing department.
     *
     * @param Department $department
     * @param array $data
     * @return bool
     */
    public function update(Department $department, array $data): bool
    {
        try {
            return $department->update($data);
        } catch (\Exception $e) {
           Log::error('Failed to update department: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update department. Please try again.');
        }
    }

    /**
     * Delete a department.
     *
     * @param Department $department
     * @return bool|null
     */
    public function delete(Department $department): ?bool
    {
        try {
            return $department->delete();
        } catch (\Exception $e) {
           Log::error('Failed to delete department: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete department. Please try again.');
        }
    }

    /**
     * Restore a soft-deleted department.
     *
     * @param Department $department
     * @return bool
     */
    public function restore(Department $department): bool
    {
        try {
            return $department->restore();
        } catch (\Exception $e) {
           Log::error('Failed to restore department: ' . $e->getMessage());
            throw new \RuntimeException('Failed to restore department. Please try again.');
        }
    }

    /**
     * Permanently delete a department.
     *
     * @param Department $department
     * @return bool
     */
    public function forceDelete(Department $department): bool
    {
        try {
            return $department->forceDelete();
        } catch (\Exception $e) {
           Log::error('Failed to force delete department: ' . $e->getMessage());
            throw new \RuntimeException('Failed to permanently delete department. Please try again.');
        }
    }

    /**
     * Check if department code is unique within facility.
     *
     * @param string $departmentCode
     * @param int $facilityId
     * @param int|null $excludeId
     * @return bool
     */
    public function isDepartmentCodeUnique(string $departmentCode, int $facilityId, ?int $excludeId = null): bool
    {
        try {
            $query = Department::where('department_code', $departmentCode)
                ->where('facility_id', $facilityId);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            return !$query->exists();
        } catch (\Exception $e) {
           Log::error('Failed to check department code uniqueness: ' . $e->getMessage());
            return false;
        }
    }
}