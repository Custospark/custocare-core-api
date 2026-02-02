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

            // Lock active row to avoid race conditions on rapid toggles
            $active = StaffPresence::query()
                ->where('staff_id', $staffId)
                ->where('facility_id', $facilityId)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            // If active exists and same status -> just update metadata/note
            if ($active && $active->status === $status) {
                $active->update([
                    'updated_by' => $updatedBy,
                    'updated_by_user_id' => $updatedByUserId,
                    'note' => $note,
                ]);
                return $active->refresh();
            }

            // Close previous active session if exists
            if ($active) {
                $active->update([
                    'ended_at' => $now,
                    'updated_by' => $updatedBy,
                    'updated_by_user_id' => $updatedByUserId,
                ]);
            }

            // Create new session
            return StaffPresence::create([
                'staff_id' => $staffId,
                'facility_id' => $facilityId,
                'status' => $status,
                'started_at' => $now,
                'ended_at' => null,
                'updated_by' => $updatedBy,
                'updated_by_user_id' => $updatedByUserId,
                'note' => $note,
            ]);
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
