<?php

namespace App\Services\StaffPresence;

use App\Models\StaffPresence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StaffPresenceService
{
    /**
     * Set presence for a staff member at a facility.
     * Creates a new presence "session" and closes previous active one (if any).
     */
   public function setPresence(
    int $staffId,
    int $facilityId,
    string $status,
    string $updatedBy = 'staff',
    ?int $updatedByUserId = null,
    ?string $note = null
): StaffPresence {
    return DB::transaction(function () use ($staffId, $facilityId, $status, $updatedBy, $updatedByUserId, $note) {
        
        $now = Carbon::now();
        
        // Find or create the single presence record for this staff at this facility
        $presence = StaffPresence::query()
            ->where('staff_id', $staffId)
            ->where('facility_id', $facilityId)
            ->lockForUpdate()
            ->first();
        
        if ($presence) {
            // Update the existing record
            $presence->update([
                'status' => $status,
                'updated_by' => $updatedBy,
                'updated_by_user_id' => $updatedByUserId,
                'note' => $note,
                'updated_at' => $now,
                // Keep started_at as the original start time
                // No ended_at since this is a continuous single record
            ]);
        } else {
            // Create new record if none exists
            $presence = StaffPresence::create([
                'staff_id' => $staffId,
                'facility_id' => $facilityId,
                'status' => $status,
                'started_at' => $now,
                'ended_at' => null, // Always null since this is a continuous record
                'updated_by' => $updatedBy,
                'updated_by_user_id' => $updatedByUserId,
                'note' => $note,
            ]);
        }
        
        return $presence->refresh();
    });
}

    /**
     * Get active presence for staff in facility.
     */
    public function getActivePresence(int $staffId, int $facilityId): ?StaffPresence
    {
        return StaffPresence::query()
            ->where('staff_id', $staffId)
            ->where('facility_id', $facilityId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * List presences eligible for forwarding.
     */
    public function listEligibleForForwarding(int $facilityId)
    {
        return StaffPresence::query()
            ->where('facility_id', $facilityId)
            ->active()
            ->eligibleForForwarding()
            ->with(['staff']) // you can add staff->user relation if you have it
            ->orderByRaw("FIELD(status,'on_duty','busy')") // on_duty first
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
