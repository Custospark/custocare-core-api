<?php

namespace App\Services\StaffSpaceAssignmentService;

use App\Models\FacilitySpace;
use App\Models\StaffSpaceAssignment;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffSpaceAssignmentService
{
    /**
     * Assign a staff member to a space.
     */
    public function assignStaffToSpace(
        int $staffId,
        int $facilityId,
        int $spaceId,
        ?int $byUserId = null,
        ?string $note = null
    ): StaffSpaceAssignment {
        return DB::transaction(function () use ($staffId, $facilityId, $spaceId, $byUserId, $note) {
            // Validate staff exists
            $staff = Staff::findOrFail($staffId);

            // Validate space exists and belongs to facility
            $space = FacilitySpace::query()
                ->where('id', $spaceId)
                ->where('facility_id', $facilityId)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if space is active
            if (!$space->is_active) {
                throw new \Exception('The selected space is not active and cannot be assigned.');
            }


            $now = now();

            // Get any active assignment for this staff in this facility
            $existingAssignment = StaffSpaceAssignment::query()
                ->forStaff($staffId)
                ->forFacility($facilityId)
                ->active()
                ->lockForUpdate()
                ->first();

            // If staff is already assigned to the same space, just update note
            if ($existingAssignment && (int)$existingAssignment->space_id === (int)$spaceId) {
                Log::info('Staff already assigned to this space, updating note', [
                    'staff_id' => $staffId,
                    'space_id' => $spaceId,
                    'assignment_id' => $existingAssignment->id,
                ]);

                $existingAssignment->update([
                    'note' => $note,
                    'assigned_at' => $now, // Refresh timestamp
                ]);

                return $existingAssignment->refresh();
            }

            // Release any existing assignment in this facility
            if ($existingAssignment) {
                Log::info('Releasing existing space assignment', [
                    'staff_id' => $staffId,
                    'old_space_id' => $existingAssignment->space_id,
                    'new_space_id' => $spaceId,
                ]);

                $existingAssignment->update([
                    'released_at' => $now,
                    'released_by_user_id' => $byUserId,
                ]);
            }

            // Create new assignment
            $newAssignment = StaffSpaceAssignment::create([
                'staff_id' => $staffId,
                'facility_id' => $facilityId,
                'space_id' => $spaceId,
                'assigned_at' => $now,
                'released_at' => null,
                'assigned_by_user_id' => $byUserId,
                'released_by_user_id' => null,
                'note' => $note,
            ]);

            Log::info('New space assignment created', [
                'assignment_id' => $newAssignment->id,
                'staff_id' => $staffId,
                'space_id' => $spaceId,
                'facility_id' => $facilityId,
            ]);

            return $newAssignment;
        });
    }

    /**
     * Release a staff member's space assignment.
     */
    public function releaseStaffSpace(
        int $staffId,
        int $facilityId,
        ?int $byUserId = null
    ): ?StaffSpaceAssignment {
        return DB::transaction(function () use ($staffId, $facilityId, $byUserId) {
            $activeAssignment = StaffSpaceAssignment::query()
                ->forStaff($staffId)
                ->forFacility($facilityId)
                ->active()
                ->lockForUpdate()
                ->first();

            if (!$activeAssignment) {
                Log::info('No active assignment to release', [
                    'staff_id' => $staffId,
                    'facility_id' => $facilityId,
                ]);
                return null;
            }

            $activeAssignment->update([
                'released_at' => now(),
                'released_by_user_id' => $byUserId,
            ]);

            Log::info('Space assignment released', [
                'assignment_id' => $activeAssignment->id,
                'staff_id' => $staffId,
                'space_id' => $activeAssignment->space_id,
                'facility_id' => $facilityId,
            ]);

            return $activeAssignment->refresh();
        });
    }

    /**
     * Get current space assignment for a staff member in a facility.
     */
    public function getCurrentSpaceForStaff(int $staffId, int $facilityId): ?StaffSpaceAssignment
    {
        return StaffSpaceAssignment::query()
            ->forStaff($staffId)
            ->forFacility($facilityId)
            ->active()
            ->with([
                'space',
                'assignedBy:id,first_name,last_name',
            ])
            ->latest('assigned_at')
            ->first();
    }

    /**
     * List all spaces with their current occupancy status.
     */
    public function listCurrentOccupancy(
        int $facilityId,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $page = request()->input('page', 1);
        $cacheKey = 'spaces_occupancy_' . $facilityId . '_' . md5(serialize($filters)) . '_' . $perPage . '_' . $page;

        return Cache::remember($cacheKey, 30, function () use ($facilityId, $filters, $perPage) {
            $query = FacilitySpace::query()
                ->forFacility($facilityId)
                ->active()
                ->with([
                    'currentAssignment' => function ($query) use ($facilityId) {
                        $query->with([
                            'staff.user:id,first_name,last_name',
                            'staff.facilityStaffRoles' => function ($q) use ($facilityId) {
                                $q->where('facility_id', $facilityId)
                                  ->where('assignment_status', 'active')
                                  ->with('facility:id,facility_name');
                            },
                            'assignedBy:id,first_name,last_name',
                        ]);
                    },
                ])
                ->withCount('activeAssignments')
                ->select([
                    'id',
                    'facility_id',
                    'name',
                    'type',
                    'floor',
                    'building',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]);

            // Apply filters using scopes
            $query->byType($filters['space_type'] ?? null)
                  ->byFloor($filters['floor'] ?? null)
                  ->byBuilding($filters['building'] ?? null)
                  ->search($filters['search'] ?? null);

            // Order by building, floor, name
            $query->orderBy('building')
                  ->orderBy('floor')
                  ->orderBy('name');

            return $query->paginate($perPage);
        });
    }

    /**
     * Get available (unoccupied) spaces for a facility.
     */
    public function getAvailableSpaces(
        int $facilityId,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = FacilitySpace::query()
            ->forFacility($facilityId)
            ->active()
            ->unoccupied()
            ->select([
                'id',
                'facility_id',
                'name',
                'type',
                'floor',
                'building',
                'is_active',
            ]);

        // Apply filters
        $query->byType($filters['space_type'] ?? null)
              ->byFloor($filters['floor'] ?? null)
              ->byBuilding($filters['building'] ?? null)
              ->search($filters['search'] ?? null);

        // Order by building, floor, name
        $query->orderBy('building')
              ->orderBy('floor')
              ->orderBy('name');

        return $query->paginate($perPage);
    }

    /**
     * Get occupancy statistics for a facility.
     */
    public function getOccupancyStatistics(int $facilityId, array $filters = []): array
    {
        $query = FacilitySpace::query()
            ->forFacility($facilityId)
            ->active();

        // Apply same filters as list for consistency
        $query->byType($filters['space_type'] ?? null)
              ->byFloor($filters['floor'] ?? null)
              ->byBuilding($filters['building'] ?? null)
              ->search($filters['search'] ?? null);

        $totalSpaces = $query->count();
        $occupiedSpaces = (clone $query)->occupied()->count();
        $availableSpaces = $totalSpaces - $occupiedSpaces;

        $occupancyRate = $totalSpaces > 0 
            ? round(($occupiedSpaces / $totalSpaces) * 100, 2) 
            : 0;

        return [
            'total_spaces' => $totalSpaces,
            'occupied_spaces' => $occupiedSpaces,
            'available_spaces' => $availableSpaces,
            'occupancy_rate' => $occupancyRate,
        ];
    }

    /**
     * Check if a staff member can be assigned to a space.
     */
    public function canAssignStaffToSpace(int $staffId, int $spaceId, int $facilityId): array
    {
        $space = FacilitySpace::find($spaceId);

        if (!$space) {
            return ['can_assign' => false, 'reason' => 'Space not found.'];
        }

        if ($space->facility_id !== $facilityId) {
            return ['can_assign' => false, 'reason' => 'Space does not belong to the specified facility.'];
        }

        if (!$space->is_active) {
            return ['can_assign' => false, 'reason' => 'Space is not active.'];
        }


        return ['can_assign' => true, 'reason' => null];
    }
}