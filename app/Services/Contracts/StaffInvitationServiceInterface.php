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
    public function getAllInvitations(array $filters = [], int $perPage = 20): array;

    /**
     * Get invitation by ID.
     */
    public function getInvitationById(int $id): array;

    /**
     * Get invitation by UUID.
     */
    public function getInvitationByUuid(string $uuid): array;

    /**
     * Create a new staff invitation.
     */
    public function createInvitation(array $data, ?int $invitedByStaffId = null): array;

    /**
     * Update an existing staff invitation.
     */
    public function updateInvitation(int $id, array $data): array;

    /**
     * Delete a staff invitation.
     */
    public function deleteInvitation(int $id): array;

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(int $id): array;

    /**
     * Decline an invitation.
     */
    public function declineInvitation(int $id): array;

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): array;

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): array;

    /**
     * Get invitations for a specific staff member.
     */
    public function getInvitationsByStaff(int $staffId, array $filters = []): array;

    /**
     * Get invitations for a specific facility.
     */
    public function getInvitationsByFacility(int $facilityId, array $filters = []): array;

    /**
     * Process expired invitations.
     */
    public function processExpiredInvitations(): array;

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingInvitationsForStaff(int $staffId): array;
}