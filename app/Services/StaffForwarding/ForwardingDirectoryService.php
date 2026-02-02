<?php

namespace App\Services\StaffForwarding;

use App\Models\StaffPresence;
use App\Models\StaffSpaceAssignment;

class ForwardingDirectoryService
{
    public function list(int $facilityId, ?array $allowedStaffIds = null)
    {
        // 1) Get eligible presences (on_duty/busy) active only
        $presenceQuery = StaffPresence::query()
            ->where('facility_id', $facilityId)
            ->whereNull('ended_at')
            ->whereIn('status', ['on_duty', 'busy'])
            ->with(['staff']); // add staff->user if you have it

        // Optional: filter to allowed staff set (role allowed for visit phase)
        if (is_array($allowedStaffIds) && count($allowedStaffIds) > 0) {
            $presenceQuery->whereIn('staff_id', $allowedStaffIds);
        }

        $presences = $presenceQuery->get();

        if ($presences->isEmpty()) {
            return collect();
        }

        $staffIds = $presences->pluck('staff_id')->unique()->values();

        // 2) Fetch current room assignments for these staff (active only)
        $assignments = StaffSpaceAssignment::query()
            ->where('facility_id', $facilityId)
            ->whereNull('released_at')
            ->whereIn('staff_id', $staffIds)
            ->with(['space'])
            ->get()
            ->keyBy('staff_id');

        // 3) Build combined rows
        return $presences->map(function ($presence) use ($assignments) {
            $assignment = $assignments->get($presence->staff_id);

            return [
                'staff_id' => $presence->staff_id,
                'staff' => $presence->staff, // contains staff fields
                'presence' => [
                    'status' => $presence->status,
                    'started_at' => $presence->started_at,
                    'updated_at' => $presence->updated_at,
                ],
                'location' => $assignment ? [
                    'space_id' => $assignment->space_id,
                    'name' => $assignment->space->name,
                    'type' => $assignment->space->type,
                    'floor' => $assignment->space->floor,
                    'building' => $assignment->space->building,
                    'assigned_at' => $assignment->assigned_at,
                ] : null,
            ];
        })->sortBy(function ($row) {
            // on_duty first, then busy
            return $row['presence']['status'] === 'on_duty' ? 0 : 1;
        })->values();
    }
}
