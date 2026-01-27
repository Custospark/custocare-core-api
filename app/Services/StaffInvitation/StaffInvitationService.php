<?php

namespace App\Services\StaffInvitation;

use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Services\Contracts\StaffInvitationServiceInterface;
use App\Repositories\Contracts\StaffInvitationRepositoryInterface;
use App\Services\Contracts\FacilityStaffRoleServiceInterface;
use App\Services\FacilityStaffService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StaffInvitationService implements StaffInvitationServiceInterface
{
    /**
     * The repository instance.
     */
    protected StaffInvitationRepositoryInterface $repository;
    
    /**
     * The facility staff service instance.
     */
    protected FacilityStaffRoleServiceInterface $facilityStaffService;

    /**
     * Create a new service instance.
     */
    public function __construct(
        StaffInvitationRepositoryInterface $repository,
        FacilityStaffRoleServiceInterface $facilityStaffService
    ) {
        $this->repository = $repository;
        $this->facilityStaffService = $facilityStaffService;
    }

    /**
     * Get all staff invitations.
     */
    public function getAllInvitations(array $filters = [], int $perPage = 20):LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get invitation by ID.
     */
    public function getInvitationById(int $id): ?StaffInvitation
    {
        return $this->repository->findById($id);
    }

    /**
     * Get invitation by UUID.
     */
    public function getInvitationByUuid(string $uuid): ?StaffInvitation
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * Create a new staff invitation.
     */
    public function createInvitation(array $data, $invitedByStaffId = null): StaffInvitation
    {
        // Add invited_by_staff_id if provided
        if ($invitedByStaffId) {
            $data['invited_by_staff_id'] = (int)$invitedByStaffId;
        } 
        $invitedByStaffId = Staff::where('user_id',Auth::id())->value('id'); 
        if (!$invitedByStaffId) {
            throw new \Exception('Inviter staff context not found.');
        }
        // Prevent self-invitation into a facility
        if ((int) $data['staff_id'] === (int) $invitedByStaffId) {
            throw new \Exception('You cannot invite yourself to a facility.');
        }        
        // Check for duplicate pending/accepted invitations
        $duplicateExists = $this->repository->duplicateExists(
            $data['staff_id'],
            $data['facility_id'],
            $data['department_id'] ?? null
        );
        
        if ($duplicateExists) {
            throw new \Exception('An active invitation already exists for this staff member at the specified facility/department.');
        }
        
        // Set default expiration (e.g., 7 days from now) if not provided
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = now()->addDays(7);
        }
        
        // Generate UUID if not provided
        if (!isset($data['invitation_uuid'])) {
            $data['invitation_uuid'] = Str::uuid();
        }
        
        // Set sent timestamp
        $data['sent_at'] = now();
        Log::info($data);
        
        return $this->repository->create($data);
    }

    /**
     * Update an existing staff invitation.
     */
    public function updateInvitation(int $id, array $data): StaffInvitation
    {
        $existingInvitation = $this->repository->findById($id);
        
        if (!$existingInvitation) {
            throw new \Exception('Invitation not found.');
        }
        
        // Prevent updates to certain fields if invitation is not pending
        if (!$existingInvitation->isPending()) {
            $nonPendingFields = ['staff_id', 'facility_id', 'department_id', 'role_id', 'expires_at'];
            foreach ($nonPendingFields as $field) {
                if (isset($data[$field]) && $data[$field] != $existingInvitation->$field) {
                    throw new \Exception('Cannot update invitation details after it has been responded to.');
                }
            }
        }
        
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a staff invitation.
     */
    public function deleteInvitation(int $id): bool
    {
        $existingInvitation = $this->repository->findById($id);
        
        if (!$existingInvitation) {
            throw new \Exception('Invitation not found.');
        }
        
        // Prevent deletion of accepted invitations
        if ($existingInvitation->status === 'accepted') {
            throw new \Exception('Cannot delete an accepted invitation.');
        }
        
        return $this->repository->delete($id);
    }

    /**
     * Accept an invitation.
     */
    // public function acceptInvitation(int $id): array
    // {
    //     return DB::transaction(function () use ($id) {
    //         $invitation = $this->repository->findById($id);
            
    //         if (!$invitation) {
    //             throw new \Exception('Invitation not found.');
    //         }
            
    //         // Check if invitation can be accepted
    //         if (!$invitation->canBeAccepted()) {
    //             $message = $invitation->isExpired() 
    //                 ? 'This invitation has expired.' 
    //                 : 'This invitation cannot be accepted in its current state.';
    //             throw new \Exception($message);
    //         }
            
    //         // Update invitation status
    //         $updatedInvitation = $this->repository->updateStatus($id, 'accepted');
            
    //         // Create facility-staff assignment
    //         // $assignment = $this->facilityStaffService->createAssignment($updatedInvitation);
            
    //         return [
    //             'invitation' => $updatedInvitation,
    //             // 'assignment' => $assignment
    //         ];
    //     });
    // }
    public function acceptInvitation(int $id): array
    {
        return DB::transaction(function () use ($id) {
            // 1. Fetch and lock the invitation row
            $invitation = StaffInvitation::lockForUpdate()->find($id);
            
            if (!$invitation) {
                throw new \Exception('Invitation not found.');
            }
            
            // 2. Validate invitation can be accepted
            if (!$invitation->canBeAccepted()) {
                $message = $invitation->isExpired() 
                    ? 'This invitation has expired.' 
                    : 'This invitation cannot be accepted in its current state.';
                throw new \Exception($message);
            }
            
            // 3. Check if assignment already exists (idempotency check)
            $existingAssignment = FacilityStaffRole::where('facility_id', $invitation->facility_id)
                ->where('staff_id', $invitation->staff_id)
                ->where('role_code', $invitation->role_code)
                ->whereNull('effective_to')
                ->first();
            
            if ($existingAssignment) {
                // If assignment exists, just update invitation status
                $invitation->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
                
                return [
                    'invitation' => $invitation->fresh(),
                    'assignment' => $existingAssignment,
                    'was_existing' => true
                ];
            }
            
            // 4. Create facility staff role assignment FIRST
            $assignment = $this->createFacilityStaffRole($invitation);
            
            // 5. Update invitation status AFTER successful assignment creation
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);
            
            // 6. Link the invitation to the assignment (optional but recommended)
            $assignment->update([
                'staff_invitation_id' => $invitation->id
            ]);
            
            Log::info('Invitation accepted and staff assigned', [
                'invitation_id' => $invitation->id,
                'assignment_id' => $assignment->id,
                'staff_id' => $invitation->staff_id,
                'facility_id' => $invitation->facility_id,
            ]);
            
            return [
                'invitation' => $invitation->fresh(),
                'assignment' => $assignment->fresh(),
                'was_existing' => false
            ];
        });
    }

   /**
     * Create facility staff role from invitation data
     * 
     * @param StaffInvitation $invitation
     * @return FacilityStaffRole
     * @throws \Exception
     */
    private function createFacilityStaffRole(StaffInvitation $invitation): FacilityStaffRole
    {
        try {
            // Prepare department IDs array
            $departmentIds = $invitation->department_id 
                ? [$invitation->department_id] 
                : null;
            
            // Decode module codes from invitation
            $moduleCodes = is_string($invitation->module_code) 
                ? json_decode($invitation->module_code, true) 
                : $invitation->module_code;
            
            // Create the assignment
            $assignment = FacilityStaffRole::create([
                'assignment_uuid' => Str::uuid(),
                'facility_id' => $invitation->facility_id,
                'staff_id' => $invitation->staff_id,
                'role_code' => $invitation->role_code,
                'department_ids' => $departmentIds,
                'module_code' => $moduleCodes,
                'is_primary_facility' => $this->shouldBePrimaryFacility($invitation->staff_id),
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'assignment_status' => 'active',
                'credentialing_completed_at' => null, // To be completed later
                'privileging_approved_at' => null, // To be approved later
                'created_by_staff_id' => $invitation->invited_by_staff_id,
                'staff_invitation_id' => null, // Will be set after invitation update
                'metadata' => [
                    'created_from_invitation' => true,
                    'invitation_uuid' => $invitation->invitation_uuid,
                    'accepted_at' => now()->toIso8601String(),
                ],
            ]);
            
            return $assignment;
            
        } catch (\Exception $e) {
            Log::error('Failed to create facility staff role', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('Failed to create staff assignment: ' . $e->getMessage());
        }
    }

        /**
         * Determine if this should be the staff's primary facility
         * 
         * @param int $staffId
         * @return bool
         */
        private function shouldBePrimaryFacility(int $staffId): bool
        {
            // Check if staff has any other active facility assignments
            $hasExistingFacilities = FacilityStaffRole::where('staff_id', $staffId)
                ->where('assignment_status', 'active')
                ->whereNull('effective_to')
                ->exists();
            
            // If no existing facilities, this becomes primary
            return !$hasExistingFacilities;
        }

        /**
         * Decline an invitation atomically
         * 
         * @param int $id
         * @return StaffInvitation
         * @throws \Exception
         */
        public function declineInvitation(int $id): StaffInvitation
        {
            return DB::transaction(function () use ($id) {
                $invitation = StaffInvitation::lockForUpdate()->find($id);
                
                if (!$invitation) {
                    throw new \Exception('Invitation not found.');
                }
                
                if (!$invitation->canBeDeclined()) {
                    throw new \Exception('This invitation cannot be declined in its current state.');
                }
                
                $invitation->update([
                    'status' => 'declined',
                    'responded_at' => now(),
                ]);
                
                Log::info('Invitation declined', [
                    'invitation_id' => $invitation->id,
                    'staff_id' => $invitation->staff_id,
                    'facility_id' => $invitation->facility_id,
                ]);
                
                return $invitation->fresh();
            });
        }
    /**
     * Decline an invitation.
     */
    // public function declineInvitation(int $id): StaffInvitation
    // {
    //     $invitation = $this->repository->findById($id);
        
    //     if (!$invitation) {
    //         throw new \Exception('Invitation not found.');
    //     }
        
    //     // Check if invitation can be declined
    //     if (!$invitation->isPending()) {
    //         throw new \Exception('Only pending invitations can be declined.');
    //     }
        
    //     return $this->repository->updateStatus($id, 'declined');
    // }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): StaffInvitation
    {
        $invitation = $this->repository->findById($id);
        
        if (!$invitation) {
            throw new \Exception('Invitation not found.');
        }
        
        // Only pending invitations can be resent
        if (!$invitation->isPending()) {
            throw new \Exception('Only pending invitations can be resent.');
        }
        
        // Update sent_at timestamp and reset expiration
        return $this->repository->update($id, [
            'sent_at' => now(),
            'expires_at' => now()->addDays(7)
        ]);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): bool
    {
        $invitation = $this->repository->findById($id);
        
        if (!$invitation) {
            throw new \Exception('Invitation not found.');
        }
        
        // Only pending invitations can be cancelled
        if (!$invitation->isPending()) {
            throw new \Exception('Only pending invitations can be cancelled.');
        }
        
        return $this->repository->delete($id);
    }

    /**
     * Get invitations for a specific staff member.
     */
    public function getInvitationsByStaff(int $staffId, array $filters = []):Collection
    {
        return $this->repository->getByStaffId($staffId, $filters);
    }

    /**
     * Get invitations for a specific facility.
     */
    public function getInvitationsByFacility(int $facilityId, array $filters = []):Collection
    {
        return $this->repository->getByFacilityId($facilityId, $filters);
    }

    /**
     * Process expired invitations.
     */
    public function processExpiredInvitations(): int
    {
        $expiredCount = 0;
        $now = now();
        
        // Get all pending invitations that have expired
        $expiredInvitations = StaffInvitation::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();
        
        foreach ($expiredInvitations as $invitation) {
            $this->repository->updateStatus($invitation->id, 'expired');
            $expiredCount++;
        }
        
        return $expiredCount;
    }

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingInvitationsForStaff(int $staffId):Collection
    {
        return $this->repository->getPendingByStaffId($staffId);
    }

    /**
     * Validate invitation can be accepted.
     */
    public function validateInvitationCanBeAccepted(StaffInvitation $invitation): bool
    {
        return $invitation->isPending() && !$invitation->isExpired();
    }

    /**
     * Check if staff has existing assignment.
     */
    public function staffHasExistingAssignment(int $staffId, int $facilityId): bool
    {
        // return $this->facilityStaffService->staffHasActiveAssignment($staffId, $facilityId);
        return true;
    }
}