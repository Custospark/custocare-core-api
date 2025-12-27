<?php

namespace App\Services\StaffInvitation;

use App\Models\StaffInvitation;
use App\Services\Contracts\StaffInvitationServiceInterface;
use App\Repositories\Contracts\StaffInvitationRepositoryInterface;
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
     * Create a new service instance.
     */
    public function __construct(StaffInvitationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all staff invitations.
     */
    public function getAllInvitations(array $filters = [], int $perPage = 20): array
    {
        try {
            $invitations = $this->repository->getAll($filters, $perPage);
            
            return [
                'success' => true,
                'data' => $invitations,
                'message' => 'Invitations retrieved successfully.',
                'meta' => [
                    'total' => $invitations->total(),
                    'per_page' => $invitations->perPage(),
                    'current_page' => $invitations->currentPage(),
                    'last_page' => $invitations->lastPage(),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve staff invitations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invitations. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => []
            ];
        }
    }

    /**
     * Get invitation by ID.
     */
    public function getInvitationById(int $id): array
    {
        try {
            $invitation = $this->repository->findById($id);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $invitation,
                'message' => 'Invitation retrieved successfully.'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve staff invitation by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Get invitation by UUID.
     */
    public function getInvitationByUuid(string $uuid): array
    {
        try {
            $invitation = $this->repository->findByUuid($uuid);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['uuid' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $invitation,
                'message' => 'Invitation retrieved successfully.'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve staff invitation by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Create a new staff invitation.
     */
    public function createInvitation(array $data, ?int $invitedByStaffId = null): array
    {
        try {
            // Validate required relationships exist (these would be validated in Request)
            // Add invited_by_staff_id if provided
            if ($invitedByStaffId) {
                $data['invited_by_staff_id'] = $invitedByStaffId;
            }
            
            // Check for duplicate pending/accepted invitations
            $duplicateExists = $this->repository->duplicateExists(
                $data['staff_id'],
                $data['facility_id'],
                $data['department_id'] ?? null
            );
            
            if ($duplicateExists) {
                return [
                    'success' => false,
                    'message' => 'An active invitation already exists for this staff member at the specified facility/department.',
                    'errors' => ['duplicate' => ['Duplicate invitation detected.']],
                    'data' => null
                ];
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
            
            $invitation = $this->repository->create($data);
            
            // Here you would typically:
            // 1. Send notification email to staff member
            // 2. Log the invitation creation
            // 3. Trigger any related events
            
            Log::info('Staff invitation created successfully', [
                'invitation_id' => $invitation->id,
                'staff_id' => $invitation->staff_id,
                'facility_id' => $invitation->facility_id
            ]);
            
            return [
                'success' => true,
                'data' => $invitation,
                'message' => 'Invitation created and sent successfully.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create staff invitation', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Update an existing staff invitation.
     */
    public function updateInvitation(int $id, array $data): array
    {
        try {
            // Check if invitation exists
            $existingInvitation = $this->repository->findById($id);
            
            if (!$existingInvitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Prevent updates to certain fields if invitation is not pending
            if (!$existingInvitation->isPending()) {
                $nonPendingFields = ['staff_id', 'facility_id', 'department_id', 'role_id', 'expires_at'];
                foreach ($nonPendingFields as $field) {
                    if (isset($data[$field]) && $data[$field] != $existingInvitation->$field) {
                        return [
                            'success' => false,
                            'message' => 'Cannot update invitation details after it has been responded to.',
                            'errors' => [$field => ['This field cannot be modified for non-pending invitations.']],
                            'data' => null
                        ];
                    }
                }
            }
            
            $invitation = $this->repository->update($id, $data);
            
            Log::info('Staff invitation updated successfully', [
                'invitation_id' => $id,
                'updated_fields' => array_keys($data)
            ]);
            
            return [
                'success' => true,
                'data' => $invitation,
                'message' => 'Invitation updated successfully.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to update staff invitation', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Delete a staff invitation.
     */
    public function deleteInvitation(int $id): array
    {
        try {
            // Check if invitation exists
            $existingInvitation = $this->repository->findById($id);
            
            if (!$existingInvitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Prevent deletion of accepted invitations without special permissions
            if ($existingInvitation->status === 'accepted') {
                return [
                    'success' => false,
                    'message' => 'Cannot delete an accepted invitation.',
                    'errors' => ['status' => ['Accepted invitations cannot be deleted.']],
                    'data' => null
                ];
            }
            
            $result = $this->repository->delete($id);
            
            if ($result) {
                Log::info('Staff invitation deleted successfully', ['invitation_id' => $id]);
                
                return [
                    'success' => true,
                    'message' => 'Invitation deleted successfully.',
                    'data' => null
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete invitation.',
                'errors' => ['system' => ['Failed to delete the invitation.']],
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to delete staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(int $id): array
    {
        try {
            $invitation = $this->repository->findById($id);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Check if invitation can be accepted
            if (!$invitation->canBeAccepted()) {
                $message = $invitation->isExpired() 
                    ? 'This invitation has expired.' 
                    : 'This invitation cannot be accepted in its current state.';
                    
                return [
                    'success' => false,
                    'message' => $message,
                    'errors' => ['status' => [$message]],
                    'data' => null
                ];
            }
            
            // Use transaction for business logic
            DB::beginTransaction();
            
            try {
                // Update invitation status
                $updatedInvitation = $this->repository->updateStatus($id, 'accepted');
                
                // Here you would typically:
                // 1. Assign staff to facility/department/role
                // 2. Create staff assignment record
                // 3. Send notification to inviter
                // 4. Log the acceptance
                
                DB::commit();
                
                Log::info('Staff invitation accepted successfully', [
                    'invitation_id' => $id,
                    'staff_id' => $updatedInvitation->staff_id,
                    'facility_id' => $updatedInvitation->facility_id
                ]);
                
                return [
                    'success' => true,
                    'data' => $updatedInvitation,
                    'message' => 'Invitation accepted successfully.'
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to accept staff invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to accept invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Decline an invitation.
     */
    public function declineInvitation(int $id): array
    {
        try {
            $invitation = $this->repository->findById($id);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Check if invitation can be declined
            if (!$invitation->isPending()) {
                return [
                    'success' => false,
                    'message' => 'This invitation cannot be declined in its current state.',
                    'errors' => ['status' => ['Only pending invitations can be declined.']],
                    'data' => null
                ];
            }
            
            $updatedInvitation = $this->repository->updateStatus($id, 'declined');
            
            Log::info('Staff invitation declined successfully', [
                'invitation_id' => $id,
                'staff_id' => $updatedInvitation->staff_id
            ]);
            
            return [
                'success' => true,
                'data' => $updatedInvitation,
                'message' => 'Invitation declined successfully.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to decline staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to decline invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Resend an invitation.
     */
    public function resendInvitation(int $id): array
    {
        try {
            $invitation = $this->repository->findById($id);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Only pending invitations can be resent
            if (!$invitation->isPending()) {
                return [
                    'success' => false,
                    'message' => 'Only pending invitations can be resent.',
                    'errors' => ['status' => ['Cannot resend non-pending invitation.']],
                    'data' => null
                ];
            }
            
            // Update sent_at timestamp
            $updatedInvitation = $this->repository->update($id, [
                'sent_at' => now(),
                'expires_at' => now()->addDays(7) // Reset expiration
            ]);
            
            // Here you would typically:
            // 1. Resend notification email
            // 2. Log the resend action
            
            Log::info('Staff invitation resent successfully', ['invitation_id' => $id]);
            
            return [
                'success' => true,
                'data' => $updatedInvitation,
                'message' => 'Invitation resent successfully.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to resend staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to resend invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(int $id): array
    {
        try {
            $invitation = $this->repository->findById($id);
            
            if (!$invitation) {
                return [
                    'success' => false,
                    'message' => 'Invitation not found.',
                    'errors' => ['id' => ['The specified invitation does not exist.']],
                    'data' => null
                ];
            }
            
            // Only pending invitations can be cancelled
            if (!$invitation->isPending()) {
                return [
                    'success' => false,
                    'message' => 'Only pending invitations can be cancelled.',
                    'errors' => ['status' => ['Cannot cancel non-pending invitation.']],
                    'data' => null
                ];
            }
            
            $result = $this->repository->delete($id);
            
            if ($result) {
                Log::info('Staff invitation cancelled successfully', ['invitation_id' => $id]);
                
                return [
                    'success' => true,
                    'message' => 'Invitation cancelled successfully.',
                    'data' => null
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to cancel invitation.',
                'errors' => ['system' => ['Failed to cancel the invitation.']],
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to cancel staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to cancel invitation. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => null
            ];
        }
    }

    /**
     * Get invitations for a specific staff member.
     */
    public function getInvitationsByStaff(int $staffId, array $filters = []): array
    {
        try {
            $invitations = $this->repository->getByStaffId($staffId, $filters);
            
            return [
                'success' => true,
                'data' => $invitations,
                'message' => 'Staff invitations retrieved successfully.',
                'meta' => [
                    'total' => $invitations->count(),
                    'staff_id' => $staffId
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invitations by staff', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve staff invitations. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => []
            ];
        }
    }

    /**
     * Get invitations for a specific facility.
     */
    public function getInvitationsByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $invitations = $this->repository->getByFacilityId($facilityId, $filters);
            
            return [
                'success' => true,
                'data' => $invitations,
                'message' => 'Facility invitations retrieved successfully.',
                'meta' => [
                    'total' => $invitations->count(),
                    'facility_id' => $facilityId
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invitations by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve facility invitations. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => []
            ];
        }
    }

    /**
     * Process expired invitations.
     */
    public function processExpiredInvitations(): array
    {
        try {
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
            
            Log::info('Expired invitations processed', ['count' => $expiredCount]);
            
            return [
                'success' => true,
                'message' => "Processed {$expiredCount} expired invitations.",
                'data' => [
                    'processed_count' => $expiredCount
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to process expired invitations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to process expired invitations.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => ['processed_count' => 0]
            ];
        }
    }

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingInvitationsForStaff(int $staffId): array
    {
        try {
            $invitations = $this->repository->getPendingByStaffId($staffId);
            
            return [
                'success' => true,
                'data' => $invitations,
                'message' => 'Pending invitations retrieved successfully.',
                'meta' => [
                    'total' => $invitations->count(),
                    'staff_id' => $staffId
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending invitations for staff', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve pending invitations. Please try again later.',
                'errors' => ['system' => ['An unexpected error occurred.']],
                'data' => []
            ];
        }
    }
}