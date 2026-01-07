<?php

namespace App\Services\Contracts;

use App\Models\StaffInvitation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StaffInvitationServiceInterface
{
    /**
     * Get all staff invitations.
     */
    public function getAllInvitations(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get invitation by ID.
     */
    public function getInvitationById(int $id): ?StaffInvitation;

    /**
     * Get invitation by UUID.
     */
    public function getInvitationByUuid(string $uuid): ?StaffInvitation;

    /**
     * Create a new staff invitation.
     */
    public function createInvitation(array $data, ?int $invitedByStaffId = null): StaffInvitation;

    /**
     * Update an existing staff invitation.
     */
    public function updateInvitation(int $id, array $data): StaffInvitation;

    /**
     * Delete a staff invitation.
     */
    public function deleteInvitation(int $id): bool;

    /**
     * Accept an invitation and create facility assignment.
     */
    public function acceptInvitation(int $id): array;

    /**
     * Decline an invitation.
     */
    public function declineInvitation(int $id): StaffInvitation;

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): StaffInvitation;

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): bool;

    /**
     * Get invitations for a specific staff member.
     */
    public function getInvitationsByStaff(int $staffId, array $filters = []): Collection;

    /**
     * Get invitations for a specific facility.
     */
    public function getInvitationsByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Process expired invitations.
     */
    public function processExpiredInvitations(): int;

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingInvitationsForStaff(int $staffId): Collection;

    /**
     * Validate invitation can be accepted.
     */
    public function validateInvitationCanBeAccepted(StaffInvitation $invitation): bool;

    /**
     * Check if staff has existing assignment at facility.
     */
    public function staffHasExistingAssignment(int $staffId, int $facilityId): bool;
}