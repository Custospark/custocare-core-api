<?php

namespace App\Repositories\Contracts;

use App\Models\StaffInvitation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StaffInvitationRepositoryInterface
{
    /**
     * Find a staff invitation by ID.
     */
    public function findById(int $id): ?StaffInvitation;

    /**
     * Find a staff invitation by UUID.
     */
    public function findByUuid(string $uuid): ?StaffInvitation;

    /**
     * Get all staff invitations with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get invitations by staff ID.
     */
    public function getByStaffId(int $staffId, array $filters = []): Collection;

    /**
     * Get invitations by facility ID.
     */
    public function getByFacilityId(int $facilityId, array $filters = []): Collection;

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingByStaffId(int $staffId): Collection;

    /**
     * Create a new staff invitation.
     */
    public function create(array $data): StaffInvitation;

    /**
     * Update an existing staff invitation.
     */
    public function update(int $id, array $data): StaffInvitation;

    /**
     * Delete a staff invitation.
     */
    public function delete(int $id): bool;

    /**
     * Restore a soft-deleted staff invitation.
     */
    public function restore(int $id): bool;

    /**
     * Force delete a staff invitation.
     */
    public function forceDelete(int $id): bool;

    /**
     * Update invitation status.
     */
    public function updateStatus(int $id, string $status): StaffInvitation;

    /**
     * Check if duplicate invitation exists.
     */
    public function duplicateExists(int $staffId, int $facilityId, ?int $departmentId = null): bool;
}