<?php

namespace App\Services\StaffSpaceAssignmentService;

use App\Models\FacilitySpace;
use App\Models\StaffSpaceAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StaffSpaceAssignmentService
{
    public function assignStaffToSpace(
        int $staffId,
        int $facilityId,
        int $spaceId,
        ?int $byUserId,
        ?string $note = null
    ): StaffSpaceAssignment {
        return DB::transaction(function () use ($staffId, $facilityId, $spaceId, $byUserId, $note) {

            $space = FacilitySpace::query()
                ->where('id', $spaceId)
                ->where('facility_id', $facilityId)
                ->firstOrFail();

            if (!$space->is_active) {
                throw new \Exception('Selected space is not active.');
            }

            $now = Carbon::now();

            $active = StaffSpaceAssignment::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            // If staff already in same room, just update note/timestamp
            if ($active && (int)$active->space_id === (int)$spaceId) {
                $active->update([
                    'note' => $note,
                ]);
                return $active->refresh();
            }

            // Close existing assignment
            if ($active) {
                $active->update([
                    'released_at' => $now,
                    'released_by_user_id' => $byUserId,
                ]);
            }

            // Create new assignment
            return StaffSpaceAssignment::create([
                'staff_id' => $staffId,
                'facility_id' => $facilityId,
                'space_id' => $spaceId,
                'assigned_at' => $now,
                'released_at' => null,
                'assigned_by_user_id' => $byUserId,
                'note' => $note,
            ]);
        });
    }

    public function releaseStaffSpace(int $staffId, int $facilityId, ?int $byUserId): void
    {
        DB::transaction(function () use ($staffId, $facilityId, $byUserId) {
            $now = Carbon::now();

            $active = StaffSpaceAssignment::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();

            if (!$active) {
                return; // nothing to release
            }

            $active->update([
                'released_at' => $now,
                'released_by_user_id' => $byUserId,
            ]);
        });
    }

    public function getCurrentSpaceForStaff(int $staffId, int $facilityId): ?StaffSpaceAssignment
    {
        return StaffSpaceAssignment::query()
            ->where('staff_id', $staffId)
            ->where('facility_id', $facilityId)
            ->whereNull('released_at')
            ->with('space')
            ->first();
    }

    public function listCurrentOccupancy(int $facilityId)
    {
        return StaffSpaceAssignment::query()
            ->where('facility_id', $facilityId)
            ->whereNull('released_at')
            ->with(['staff', 'space'])
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
