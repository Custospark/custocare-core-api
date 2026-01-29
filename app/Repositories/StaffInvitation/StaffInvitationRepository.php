<?php

namespace App\Repositories\StaffInvitation;

use App\Models\StaffInvitation;
use App\Repositories\Contracts\StaffInvitationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StaffInvitationRepository implements StaffInvitationRepositoryInterface
{
    /**
     * Find a staff invitation by ID.
     */
    public function findById(int $id): ?StaffInvitation
    {
        try {
            return StaffInvitation::with(['staff', 'facility', 'department', 'role', 'invitedBy'])->find($id);
        } catch (\Exception $e) {
            // Log the exception for debugging but return null to avoid crashing
          Log::error('Failed to find staff invitation by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find a staff invitation by UUID.
     */
    public function findByUuid(string $uuid): ?StaffInvitation
    {
        try {
            return StaffInvitation::with(['staff', 'facility', 'department', 'role', 'invitedBy'])
                ->where('invitation_uuid', $uuid)
                ->first();
        } catch (\Exception $e) {
          Log::error('Failed to find staff invitation by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all staff invitations with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = StaffInvitation::with(['staff', 'facility', 'department', 'role', 'invitedBy']);

            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['staff_id'])) {
                $query->where('staff_id', $filters['staff_id']);
            }

            if (!empty($filters['department_id'])) {
                $query->where('department_id', $filters['department_id']);
            }

            if (!empty($filters['role_id'])) {
                $query->where('role_id', $filters['role_id']);
            }

            if (!empty($filters['invited_by_staff_id'])) {
                $query->where('invited_by_staff_id', $filters['invited_by_staff_id']);
            }

            // Handle date filters
            if (!empty($filters['sent_from'])) {
                $query->whereDate('sent_at', '>=', $filters['sent_from']);
            }

            if (!empty($filters['sent_to'])) {
                $query->whereDate('sent_at', '<=', $filters['sent_to']);
            }

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            return $query->paginate($perPage);
        } catch (\Exception $e) {
          Log::error('Failed to get staff invitations', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get invitations by staff ID.
     */
    public function getByStaffId(int $staffId, array $filters = []): Collection
    {
        try {
            $query = StaffInvitation::with(['facility', 'department', 'role', 'invitedBy'])
                ->where('staff_id', $staffId);

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
          Log::error('Failed to get invitations by staff ID', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get invitations by facility ID.
     */
    public function getByFacilityId(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = StaffInvitation::with(['staff', 'department', 'role', 'invitedBy'])
                ->where('facility_id', $facilityId);

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['department_id'])) {
                $query->where('department_id', $filters['department_id']);
            }

            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
          Log::error('Failed to get invitations by facility ID', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Get pending invitations for a staff member.
     */
    public function getPendingByStaffId(int $staffId): Collection
    {
        try {
            return StaffInvitation::with(['facility', 'department', 'role'])
                ->where('staff_id', $staffId)
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
          Log::error('Failed to get pending invitations by staff ID', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Create a new staff invitation.
     */
    public function create(array $data): StaffInvitation
    {
        try {
            return DB::transaction(function () use ($data) {
                // Generate UUID if not provided
                if (!isset($data['invitation_uuid'])) {
                    $data['invitation_uuid'] = \Illuminate\Support\Str::uuid();
                }

                // Set sent_at if not provided
                if (!isset($data['sent_at'])) {
                    $data['sent_at'] = now();
                }

                return StaffInvitation::create($data);
            });
        } catch (\Exception $e) {
          Log::error('Failed to create staff invitation', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw for service layer to handle
        }
    }

    /**
     * Update an existing staff invitation.
     */
    public function update(int $id, array $data): StaffInvitation
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $invitation = StaffInvitation::findOrFail($id);
                $invitation->update($data);
                return $invitation->fresh();
            });
        } catch (ModelNotFoundException $e) {
          Log::warning('Staff invitation not found for update', ['id' => $id]);
            throw $e;
        } catch (\Exception $e) {
          Log::error('Failed to update staff invitation', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a staff invitation.
     */
    public function delete(int $id): bool
    {
        try {
            $invitation = StaffInvitation::findOrFail($id);
            return $invitation->delete();
        } catch (ModelNotFoundException $e) {
          Log::warning('Staff invitation not found for deletion', ['id' => $id]);
            throw $e;
        } catch (\Exception $e) {
          Log::error('Failed to delete staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Restore a soft-deleted staff invitation.
     */
    public function restore(int $id): bool
    {
        try {
            $invitation = StaffInvitation::withTrashed()->findOrFail($id);
            return $invitation->restore();
        } catch (ModelNotFoundException $e) {
          Log::warning('Staff invitation not found for restoration', ['id' => $id]);
            throw $e;
        } catch (\Exception $e) {
          Log::error('Failed to restore staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Force delete a staff invitation.
     */
    public function forceDelete(int $id): bool
    {
        try {
            $invitation = StaffInvitation::withTrashed()->findOrFail($id);
            return $invitation->forceDelete();
        } catch (ModelNotFoundException $e) {
          Log::warning('Staff invitation not found for force deletion', ['id' => $id]);
            throw $e;
        } catch (\Exception $e) {
          Log::error('Failed to force delete staff invitation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update invitation status.
     */
    public function updateStatus(int $id, string $status): StaffInvitation
    {
        try {
            return DB::transaction(function () use ($id, $status) {
                $invitation = StaffInvitation::findOrFail($id);
                
                $updateData = ['status' => $status];
                
                // Set responded_at if status is changing to accepted or declined
                if (in_array($status, ['accepted', 'declined'])) {
                    $updateData['responded_at'] = now();
                }
                
                $invitation->update($updateData);
                return $invitation->fresh();
            });
        } catch (ModelNotFoundException $e) {
          Log::warning('Staff invitation not found for status update', ['id' => $id]);
            throw $e;
        } catch (\Exception $e) {
          Log::error('Failed to update staff invitation status', [
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if duplicate invitation exists.
     */
    public function duplicateExists(
        int $staffId, 
        int $facilityId, 
        ?int $departmentId = null,
        bool $hasTerminatedAssignment = false
    ): bool
    {
        try {
            // If staff has a terminated assignment at this facility,
            // we still check for duplicates but with different logic
            if ($hasTerminatedAssignment) {
                // For terminated staff, we allow ONE pending invitation at a time
                // Check if there's already a pending invitation that hasn't expired
                $query = StaffInvitation::query()
                    ->where('staff_id', $staffId)
                    ->where('facility_id', $facilityId)
                    ->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                    });

                if ($departmentId) {
                    $query->where('department_id', $departmentId);
                }

                // Return true if there's already a valid pending invitation
                // (We don't want multiple pending invites for terminated staff)
                return $query->exists();
            }

            // For non-terminated cases (no assignment or different status)
            // Original logic: check for accepted or valid pending invitations
            $query = StaffInvitation::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->where(function ($q) {
                    $q->where('status', 'accepted')
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'pending')
                            ->where(function ($expiry) {
                                $expiry->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', now());
                            });
                    });
                });

            if ($departmentId) {
                $query->where('department_id', $departmentId);
            }

            return $query->exists();

        } catch (\Exception $e) {
            Log::error('Failed to check for duplicate invitation', [
                'staff_id' => $staffId,
                'facility_id' => $facilityId,
                'department_id' => $departmentId,
                'has_terminated_assignment' => $hasTerminatedAssignment,
                'error' => $e->getMessage()
            ]);

            return false; // On error, default to allowing the invitation
        }
    }

}