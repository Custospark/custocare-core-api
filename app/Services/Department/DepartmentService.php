<?php

namespace App\Services\Department;

use App\Models\Department;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Services\Contracts\DepartmentServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentService implements DepartmentServiceInterface
{
    /**
     * Repository instance.
     *
     * @var DepartmentRepositoryInterface
     */
    protected DepartmentRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param DepartmentRepositoryInterface $repository
     */
    public function __construct(DepartmentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all departments with pagination.
     *
     * @param array $filters
     * @return array
     */
    public function getAllDepartments(array $filters = []): array
    {
        try {
            dd('here');
            $perPage = $filters['per_page'] ?? 20;
            $departments = $this->repository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $departments,
                'message' => 'Departments retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get all departments: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to retrieve departments. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Get department by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getDepartmentByUuid(string $uuid): array
    {
        try {
            $department = $this->repository->findByUuid($uuid);

            if (!$department) {
                return [
                    'success' => false,
                    'message' => 'Department not found.',
                    'status' => 404,
                ];
            }

            return [
                'success' => true,
                'data' => $department,
                'message' => 'Department retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get department by UUID: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to retrieve department. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Create a new department.
     *
     * @param array $data
     * @return array
     */
    public function createDepartment(array $data): array
    {
        DB::beginTransaction();

        try {
            // -------------------------------
            // 1️⃣ Validate department data
            // -------------------------------
            $validationResult = $this->validateDepartmentData($data);
            if (!$validationResult['success']) {
                DB::rollBack();
                return $validationResult;
            }

            // -------------------------------
            // 2️⃣ Ensure department code is unique within the facility
            // -------------------------------
            if (!$this->repository->isDepartmentCodeUnique($data['department_code'], $data['facility_id'])) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department code already exists in this facility.',
                    'errors' => [
                        'department_code' => ['The department code must be unique within the facility.']
                    ],
                    'status' => 422,
                ];
            }

            // -------------------------------
            // 3️⃣ Validate parent department (if provided)
            // -------------------------------
            if (!empty($data['parent_department_id'])) {
                $parent = $this->repository->findById($data['parent_department_id']);
                if (!$parent) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Parent department not found.',
                        'errors' => [
                            'parent_department_id' => ['The selected parent department does not exist.']
                        ],
                        'status' => 422,
                    ];
                }

                if ($parent->facility_id != $data['facility_id']) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Parent department must be in the same facility.',
                        'errors' => [
                            'parent_department_id' => ['Parent department must belong to the same facility.']
                        ],
                        'status' => 422,
                    ];
                }
            }

            // -------------------------------
            // 4️⃣ Prepare department data
            // -------------------------------
            $data['department_uuid'] = $data['department_uuid'] ?? (string) \Illuminate\Support\Str::uuid();

            // -------------------------------
            // 5️⃣ Create the department
            // -------------------------------
            $department = $this->repository->create($data);

            DB::commit();

            return [
                'success' => true,
                'data' => $department,
                'message' => 'Department created successfully.',
                'status' => 201,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create department: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create department. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status' => 500,
            ];
        }
    }


    /**
     * Update an existing department.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateDepartment(string $uuid, array $data): array
    {
        DB::beginTransaction();

        try {
            $department = $this->repository->findByUuid($uuid);

            if (!$department) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department not found.',
                    'status' => 404,
                ];
            }

            // Validate department data
            $validationResult = $this->validateDepartmentData($data, $department->id);
            if (!$validationResult['success']) {
                DB::rollBack();
                return $validationResult;
            }

            // Check if department code is unique within facility (excluding current department)
            if (isset($data['department_code']) && 
                !$this->repository->isDepartmentCodeUnique($data['department_code'], $data['facility_id'] ?? $department->facility_id, $department->id)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department code already exists in this facility.',
                    'errors' => ['department_code' => ['The department code must be unique within the facility.']],
                    'status' => 422,
                ];
            }

            // Validate parent department exists if provided
            if (isset($data['parent_department_id']) && $data['parent_department_id']) {
                $parentDepartment = $this->repository->findById($data['parent_department_id']);
                if (!$parentDepartment) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Parent department not found.',
                        'errors' => ['parent_department_id' => ['The selected parent department does not exist.']],
                        'status' => 422,
                    ];
                }

                // Ensure parent department is in same facility
                $facilityId = $data['facility_id'] ?? $department->facility_id;
                if ($parentDepartment->facility_id != $facilityId) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Parent department must be in the same facility.',
                        'errors' => ['parent_department_id' => ['Parent department must belong to the same facility.']],
                        'status' => 422,
                    ];
                }

                // Prevent circular reference (department cannot be its own parent)
                if ($parentDepartment->id === $department->id) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Department cannot be its own parent.',
                        'errors' => ['parent_department_id' => ['A department cannot be its own parent.']],
                        'status' => 422,
                    ];
                }
            }

            // Update the department
            $updated = $this->repository->update($department, $data);

            if (!$updated) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to update department.',
                    'status' => 500,
                ];
            }

            // Refresh department data
            $department->refresh();

            DB::commit();

            return [
                'success' => true,
                'data' => $department,
                'message' => 'Department updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update department: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to update department. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status' => 500,
            ];
        }
    }

    /**
     * Delete a department.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteDepartment(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $department = $this->repository->findByUuid($uuid);

            if (!$department) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department not found.',
                    'status' => 404,
                ];
            }

            // Check if department has child departments
            if ($department->childDepartments()->count() > 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Cannot delete department with child departments. Please reassign or delete child departments first.',
                    'status' => 422,
                ];
            }

            // Delete the department
            $deleted = $this->repository->delete($department);

            if (!$deleted) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to delete department.',
                    'status' => 500,
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Department deleted successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete department: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to delete department. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status' => 500,
            ];
        }
    }

    /**
     * Restore a soft-deleted department.
     *
     * @param string $uuid
     * @return array
     */
    public function restoreDepartment(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $department = $this->repository->findByUuid($uuid);

            if (!$department) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department not found.',
                    'status' => 404,
                ];
            }

            // Check if department is not soft deleted
            if (!$department->trashed()) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Department is not deleted.',
                    'status' => 422,
                ];
            }

            // Restore the department
            $restored = $this->repository->restore($department);

            if (!$restored) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to restore department.',
                    'status' => 500,
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $department,
                'message' => 'Department restored successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore department: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to restore department. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'status' => 500,
            ];
        }
    }

    /**
     * Get departments by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getDepartmentsByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $departments = $this->repository->getByFacilityId($facilityId, $filters);

            return [
                'success' => true,
                'data' => $departments,
                'message' => 'Departments retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get departments by facility: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to retrieve departments. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Get departments by type.
     *
     * @param string $type
     * @param array $filters
     * @return array
     */
    public function getDepartmentsByType(string $type, array $filters = []): array
    {
        try {
            $departments = $this->repository->getByType($type, $filters);

            return [
                'success' => true,
                'data' => $departments,
                'message' => 'Departments retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get departments by type: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to retrieve departments. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Validate department data before creation/update.
     *
     * @param array $data
     * @param int|null $departmentId
     * @return array
     */
    public function validateDepartmentData(array $data, ?int $departmentId = null): array
    {
        $errors = [];

        // Validate required fields for creation
        if (!$departmentId) {
            $requiredFields = ['facility_id', 'department_code', 'department_name', 'department_type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[$field] = [$field . ' is required.'];
                }
            }
        }

        // Validate department type
        if (isset($data['department_type'])) {
            $validTypes = [
                'emergency', 'intensive_care', 'surgery', 'outpatient', 'inpatient',
                'radiology', 'laboratory', 'pharmacy', 'physical_therapy', 'cardiology',
                'oncology', 'pediatrics', 'obstetrics', 'psychiatry', 'administration',
                'support_services'
            ];
            
            if (!in_array($data['department_type'], $validTypes)) {
                $errors['department_type'] = ['Invalid department type.'];
            }
        }

        // Validate status
        if (isset($data['status'])) {
            $validStatuses = ['active', 'inactive', 'temporarily_closed'];
            if (!in_array($data['status'], $validStatuses)) {
                $errors['status'] = ['Invalid status.'];
            }
        }

        // Validate numeric fields
        $numericFields = [
            'bed_count', 'treatment_room_count', 'max_concurrent_capacity',
            'average_wait_time_minutes'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $errors[$field] = [$field . ' must be a number.'];
            }
        }

        // Validate operating hours JSON
        if (isset($data['operating_hours']) && $data['operating_hours']) {
            if (!is_array($data['operating_hours']) && !$this->isValidJson($data['operating_hours'])) {
                $errors['operating_hours'] = ['Operating hours must be valid JSON.'];
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors,
                'status' => 422,
            ];
        }

        return ['success' => true];
    }

    /**
     * Check if string is valid JSON.
     *
     * @param mixed $string
     * @return bool
     */
    private function isValidJson($string): bool
    {
        if (is_array($string)) {
            return true;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}