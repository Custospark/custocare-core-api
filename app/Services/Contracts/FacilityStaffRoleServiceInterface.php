<?php

namespace App\Services\Contracts;

use App\Models\FacilityStaffRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FacilityStaffRoleServiceInterface
{
    /**
     * Get all role assignments with optional filters
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllAssignments(array $filters = []): Collection;

    /**
     * Get paginated role assignments
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedAssignments(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get role assignment by ID
     *
     * @param int $id
     * @return array
     */
    public function getAssignmentById(int $id): array;

    /**
     * Get role assignment by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getAssignmentByUuid(string $uuid): array;

    /**
     * Get assignments for a specific facility
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getAssignmentsByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get assignments for a specific staff member
     *
     * @param int $staffId
     * @param array $filters
     * @return Collection
     */
    public function getAssignmentsByStaff(int $staffId, array $filters = []): Collection;

    /**
     * Create a new role assignment
     *
     * @param array $data
     * @return array
     */
    public function createAssignment(array $data): array;

    /**
     * Update an existing role assignment
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateAssignment(int $id, array $data): array;

    /**
     * Delete a role assignment
     *
     * @param int $id
     * @return bool
     */
    public function deleteAssignment(int $id): bool;

    /**
     * Restore a soft-deleted role assignment
     *
     * @param int $id
     * @return bool
     */
    public function restoreAssignment(int $id): bool;

    /**
     * Update assignment status
     *
     * @param int $id
     * @param string $status
     * @param array $additionalData
     * @return array
     */
    public function updateAssignmentStatus(int $id, string $status, array $additionalData = []): array;

    /**
     * Get active assignments for a staff member
     *
     * @param int $staffId
     * @param string|null $date
     * @return Collection
     */
    public function getActiveAssignmentsForStaff(int $staffId, ?string $date = null): Collection;

    /**
     * Check for scheduling conflicts
     *
     * @param int $staffId
     * @param array $schedule
     * @param int|null $excludeAssignmentId
     * @return array
     */
    public function checkScheduleConflicts(int $staffId, array $schedule, ?int $excludeAssignmentId = null): array;

    /**
     * Get expiring assignments
     *
     * @param int $daysAhead
     * @return Collection
     */
    public function getExpiringAssignments(int $daysAhead = 30): Collection;

    /**
     * Update credentialing information
     *
     * @param int $id
     * @param array $credentialingData
     * @return array
     */
    public function updateCredentialing(int $id, array $credentialingData): array;

    /**
     * Validate role assignment data
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    public function validateAssignmentData(array $data, ?int $excludeId = null): array;
}