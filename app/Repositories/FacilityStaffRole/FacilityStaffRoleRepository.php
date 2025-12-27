<?php

namespace App\Repositories\FacilityStaffRole;

use App\Models\FacilityStaffRole;
use App\Repositories\Contracts\FacilityStaffRoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacilityStaffRoleRepository implements FacilityStaffRoleRepositoryInterface
{
    /**
     * Find a role assignment by ID
     */
    public function findById(int $id): ?FacilityStaffRole
    {
        try {
            return FacilityStaffRole::with(['facility', 'staff', 'createdBy', 'credentialedBy'])
                ->find($id);
        } catch (\Exception $e) {
            // Log the exception for internal monitoring
            Log::error('Failed to find facility staff role by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find a role assignment by UUID
     */
    public function findByUuid(string $uuid): ?FacilityStaffRole
    {
        try {
            return FacilityStaffRole::with(['facility', 'staff', 'createdBy', 'credentialedBy'])
                ->where('assignment_uuid', $uuid)
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find facility staff role by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all role assignments
     */
    public function all(array $filters = []): Collection
    {
        try {
            $query = FacilityStaffRole::with(['facility', 'staff']);

            // Apply filters
            $this->applyFilters($query, $filters);

            // Order by most recent effective dates
            return $query->orderBy('effective_from', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all facility staff roles', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get paginated role assignments
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = FacilityStaffRole::with(['facility', 'staff']);

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('effective_from', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate facility staff roles', [
                'per_page' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator instead of throwing
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get role assignments by facility
     */
    public function findByFacility(int $facilityId, array $filters = []): Collection
    {
        try {
            $query = FacilityStaffRole::with(['staff', 'createdBy'])
                ->where('facility_id', $facilityId);

            $this->applyFilters($query, $filters);

            return $query->orderBy('assignment_status')
                ->orderBy('effective_from', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find facility staff roles by facility', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get role assignments by staff member
     */
    public function findByStaff(int $staffId, array $filters = []): Collection
    {
        try {
            $query = FacilityStaffRole::with(['facility', 'createdBy'])
                ->where('staff_id', $staffId);

            $this->applyFilters($query, $filters);

            return $query->orderBy('is_primary_facility', 'desc')
                ->orderBy('effective_from', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find facility staff roles by staff', [
                'staff_id' => $staffId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get active role assignments for a staff member
     */
    public function getActiveAssignmentsForStaff(int $staffId, ?string $date = null): Collection
    {
        try {
            $date = $date ?? now()->format('Y-m-d');

            return FacilityStaffRole::with(['facility'])
                ->where('staff_id', $staffId)
                ->where('assignment_status', FacilityStaffRole::STATUS_ACTIVE)
                ->where('effective_from', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $date);
                })
                ->orderBy('is_primary_facility', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get active assignments for staff', [
                'staff_id' => $staffId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Create a new role assignment
     */
    public function create(array $data): FacilityStaffRole
    {
        DB::beginTransaction();
        
        try {
            $facilityStaffRole = FacilityStaffRole::create($data);
            
            DB::commit();
            
            // Reload with relationships
            return $facilityStaffRole->load(['facility', 'staff', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create facility staff role', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to create role assignment. Please try again.');
        }
    }

    /**
     * Update a role assignment
     */
    public function update(FacilityStaffRole $facilityStaffRole, array $data): FacilityStaffRole
    {
        DB::beginTransaction();
        
        try {
            $facilityStaffRole->update($data);
            
            DB::commit();
            
            return $facilityStaffRole->fresh(['facility', 'staff', 'createdBy', 'credentialedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update facility staff role', [
                'id' => $facilityStaffRole->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update role assignment. Please try again.');
        }
    }

    /**
     * Delete a role assignment
     */
    public function delete(FacilityStaffRole $facilityStaffRole): bool
    {
        try {
            return $facilityStaffRole->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete facility staff role', [
                'id' => $facilityStaffRole->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted role assignment
     */
    public function restore(FacilityStaffRole $facilityStaffRole): bool
    {
        try {
            return $facilityStaffRole->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore facility staff role', [
                'id' => $facilityStaffRole->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for duplicate active assignments
     */
    public function duplicateAssignmentExists(
        int $facilityId,
        int $staffId,
        string $roleCode,
        string $effectiveFrom,
        ?int $excludeId = null
    ): bool {
        try {
            $query = FacilityStaffRole::where('facility_id', $facilityId)
                ->where('staff_id', $staffId)
                ->where('role_code', $roleCode)
                ->where('effective_from', $effectiveFrom)
                ->whereIn('assignment_status', [
                    FacilityStaffRole::STATUS_ACTIVE,
                    FacilityStaffRole::STATUS_ON_LEAVE
                ]);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check for duplicate assignments', [
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'role_code' => $roleCode,
                'effective_from' => $effectiveFrom,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update assignment status
     */
    public function updateStatus(FacilityStaffRole $facilityStaffRole, string $status, array $additionalData = []): FacilityStaffRole
    {
        DB::beginTransaction();
        
        try {
            $data = array_merge(['assignment_status' => $status], $additionalData);
            
            $facilityStaffRole->update($data);
            
            DB::commit();
            
            return $facilityStaffRole->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update assignment status', [
                'id' => $facilityStaffRole->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update assignment status. Please try again.');
        }
    }

    /**
     * Get assignments expiring soon
     */
    public function getExpiringAssignments(int $daysAhead = 30): Collection
    {
        try {
            $expiryDate = now()->addDays($daysAhead)->format('Y-m-d');
            
            return FacilityStaffRole::with(['facility', 'staff'])
                ->where('assignment_status', FacilityStaffRole::STATUS_ACTIVE)
                ->whereNotNull('effective_to')
                ->where('effective_to', '<=', $expiryDate)
                ->where('effective_to', '>', now()->format('Y-m-d'))
                ->orderBy('effective_to')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get expiring assignments', [
                'days_ahead' => $daysAhead,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (isset($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        if (isset($filters['role_code'])) {
            $query->where('role_code', $filters['role_code']);
        }

        if (isset($filters['assignment_status'])) {
            $query->where('assignment_status', $filters['assignment_status']);
        }

        if (isset($filters['is_primary_facility'])) {
            $query->where('is_primary_facility', (bool) $filters['is_primary_facility']);
        }

        if (isset($filters['effective_from'])) {
            $query->where('effective_from', '>=', $filters['effective_from']);
        }

        if (isset($filters['effective_to'])) {
            $query->where('effective_to', '<=', $filters['effective_to']);
        }

        if (isset($filters['shift_type'])) {
            $query->where('shift_type', $filters['shift_type']);
        }

        if (isset($filters['date'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('effective_from', '<=', $filters['date'])
                    ->where(function ($subQuery) use ($filters) {
                        $subQuery->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $filters['date']);
                    });
            });
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('assignment_uuid', 'like', "%{$search}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('staff', function ($staffQuery) use ($search) {
                        $staffQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
    }
}