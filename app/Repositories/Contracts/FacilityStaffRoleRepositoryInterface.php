<?php

namespace App\Repositories\Contracts;

use App\Models\FacilityStaffRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface FacilityStaffRoleRepositoryInterface
{
    /**
     * Find a role assignment by ID
     *
     * @param int $id
     * @return FacilityStaffRole|null
     */
    public function findById(int $id): ?FacilityStaffRole;

    /**
     * Find a role assignment by UUID
     *
     * @param string $uuid
     * @return FacilityStaffRole|null
     */
    public function findByUuid(string $uuid): ?FacilityStaffRole;

    /**
     * Get all role assignments
     *
     * @param array $filters
     * @return Collection
     */
    public function all(array $filters = []): Collection;

    /**
     * Get paginated role assignments
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get role assignments by facility
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function findByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get role assignments by staff member
     *
     * @param int $staffId
     * @param array $filters
     * @return Collection
     */
    public function findByStaff(int $staffId, array $filters = []): Collection;

    /**
     * Get active role assignments for a staff member
     *
     * @param int $staffId
     * @param string $date
     * @return Collection
     */
    public function getActiveAssignmentsForStaff(int $staffId, ?string $date): Collection;

    /**
     * Create a new role assignment
     *
     * @param array $data
     * @return FacilityStaffRole
     */
    public function create(array $data): FacilityStaffRole;

    /**
     * Update a role assignment
     *
     * @param FacilityStaffRole $facilityStaffRole
     * @param array $data
     * @return FacilityStaffRole
     */
    public function update(FacilityStaffRole $facilityStaffRole, array $data): FacilityStaffRole;

    /**
     * Delete a role assignment
     *
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function delete(FacilityStaffRole $facilityStaffRole): bool;

    /**
     * Restore a soft-deleted role assignment
     *
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function restore(FacilityStaffRole $facilityStaffRole): bool;

    /**
     * Check for duplicate active assignments
     *
     * @param int $facilityId
     * @param int $staffId
     * @param string $roleCode
     * @param string $effectiveFrom
     * @param int|null $excludeId
     * @return bool
     */
    public function duplicateAssignmentExists(
        int $facilityId,
        int $staffId,
        string $roleCode,
        string $effectiveFrom,
        ?int $excludeId
    ): bool;

    /**
     * Update assignment status
     *
     * @param FacilityStaffRole $facilityStaffRole
     * @param string $status
     * @param array $additionalData
     * @return FacilityStaffRole
     */
    public function updateStatus(FacilityStaffRole $facilityStaffRole, string $status, array $additionalData = []): FacilityStaffRole;

    /**
     * Get assignments expiring soon
     *
     * @param int $daysAhead
     * @return Collection
     */
    public function getExpiringAssignments(int $daysAhead = 30): Collection;
}