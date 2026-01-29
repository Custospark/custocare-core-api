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
    return DB::transaction(function () use ($data, $invitedByStaffId) {
        // 1) Resolve inviter staff context
        if ($invitedByStaffId) {
            $data['invited_by_staff_id'] = (int) $invitedByStaffId;
        } else {
            $resolvedInviterStaffId = Staff::query()
                ->where('user_id', Auth::id())
                ->value('id');

            if (!$resolvedInviterStaffId) {
                throw new \Exception('Inviter staff context not found.');
            }

            $data['invited_by_staff_id'] = (int) $resolvedInviterStaffId;
        }

        // 2) Prevent self-invitation into a facility
        if (isset($data['staff_id']) && (int) $data['staff_id'] === (int) $data['invited_by_staff_id']) {
            throw new \Exception('You cannot invite yourself to a facility.');
        }

        $staffId = (int) $data['staff_id'];
        $facilityId = (int) $data['facility_id'];
        $departmentId = $data['department_id'] ?? null;

        // 3) Check existing assignment
        $existingAssignment = FacilityStaffRole::query()
            ->where('facility_id', $facilityId)
            ->where('staff_id', $staffId)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();

        if ($existingAssignment) {
            $activeStatuses = ['active', 'on_leave', 'suspended'];
            $assignmentStatus = $existingAssignment->assignment_status;
            
            // Block if assignment is active/on_leave/suspended
            if (in_array($assignmentStatus, $activeStatuses, true)) {
                $statusDisplay = ucfirst(str_replace('_', ' ', $assignmentStatus));
                throw new \Exception(
                    "This staff member already has an active affiliation with your facility (status: {$statusDisplay})."
                );
            }
            // If assignment is terminated, we need special handling for duplicate check
            // Continue to duplicate check with terminated flag
        }

        // 4) Check for duplicate invitations with terminated assignment consideration
        $hasTerminatedAssignment = $existingAssignment && $existingAssignment->assignment_status === 'terminated';
        
        $duplicateExists = $this->repository->duplicateExists(
            $staffId,
            $facilityId,
            $departmentId,
            $hasTerminatedAssignment
        );

        if ($duplicateExists) {
            $message = $hasTerminatedAssignment 
                ? 'An invitation already exists for this terminated staff member. Please wait for their response.'
                : 'An active invitation already exists for this staff member at the specified facility/department.';
            
            throw new \Exception($message);
        }

        // 5) Defaults
        $data['expires_at'] ??= now()->addDays(7);
        $data['invitation_uuid'] ??= (string) Str::uuid();
        $data['sent_at'] = now();

        // 6) Always include 'account' module if module_code is provided
        if (array_key_exists('module_code', $data)) {
            $modules = is_array($data['module_code']) ? $data['module_code'] : [];

            $data['module_code'] = array_values(array_unique(array_merge(
                array_map('strval', $modules),
                ['account']
            )));
        }

        Log::info('Creating staff invitation', [
            'staff_id' => $staffId,
            'facility_id' => $facilityId,
            'invited_by' => $data['invited_by_staff_id'],
            'has_terminated_assignment' => $hasTerminatedAssignment
        ]);

        return $this->repository->create($data);
    });
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

        // 3. Check if staff already has an active assignment at this facility
        $existingActiveAssignment = FacilityStaffRole::query()
            ->where('facility_id', $invitation->facility_id)
            ->where('staff_id', $invitation->staff_id)
            ->whereIn('assignment_status', ['active', 'on_leave', 'suspended'])
            ->first();

        // 4. REJECT if staff already has an active assignment (active/on_leave/suspended)
        if ($existingActiveAssignment) {
            $statusDisplay = match($existingActiveAssignment->assignment_status) {
                'active' => 'actively working',
                'on_leave' => 'on leave',
                'suspended' => 'suspended',
                default => 'associated'
            };
            
            throw new \Exception(
                "The staff member is already {$statusDisplay} at this facility."
            );
        }

        // 5. Check if staff has a terminated assignment at this facility
        $existingTerminatedAssignment = FacilityStaffRole::query()
            ->where('facility_id', $invitation->facility_id)
            ->where('staff_id', $invitation->staff_id)
            ->where('assignment_status', 'terminated')
            ->first();

        // 6. Handle terminated assignment or create new one
        if ($existingTerminatedAssignment) {
            $assignment = $this->reactivateTerminatedAssignment($existingTerminatedAssignment, $invitation);
            $wasExisting = true;
            $action = 'reactivated_terminated';
            
            Log::info('Reactivated terminated assignment with invitation data', [
                'invitation_id' => $invitation->id,
                'assignment_id' => $assignment->id,
                'staff_id' => $invitation->staff_id,
                'facility_id' => $invitation->facility_id,
                'old_role_code' => $existingTerminatedAssignment->role_code,
                'new_role_code' => $invitation->role_code,
            ]);
        } else {
            // 7. No existing assignment at all - CREATE new one
            $assignment = $this->createFacilityStaffRole($invitation);
            $wasExisting = false;
            $action = 'created_new';
            
            Log::info('Created new facility assignment', [
                'invitation_id' => $invitation->id,
                'assignment_id' => $assignment->id,
                'staff_id' => $invitation->staff_id,
                'facility_id' => $invitation->facility_id,
                'role_code' => $invitation->role_code,
            ]);
        }

        // 8. Update invitation status AFTER successful assignment creation/update
        $invitation->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
        
        // 9. Link the invitation to the assignment
        $assignment->update([
            'staff_invitation_id' => $invitation->id
        ]);
        
        return [
            'invitation' => $invitation->fresh(),
            'assignment' => $assignment->fresh(),
            'was_existing' => $wasExisting,
            'action' => $action
        ];
    });
}

    /**
     * Reactivate a terminated assignment with new invitation data
     */
    private function reactivateTerminatedAssignment(FacilityStaffRole $assignment, StaffInvitation $invitation): FacilityStaffRole
    {
        $updateData = [
            'role_code' => $invitation->role_code,
            'assignment_status' => 'active',
            'employment_status' => 'employed',
            'effective_from' => now(),
            'effective_to' => null,
            'termination_date' => null,
            'termination_reason' => null,
            'staff_invitation_id' => $invitation->id,
        ];
        
        // Update the assignment
        $assignment->update($updateData);
        
        return $assignment;
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