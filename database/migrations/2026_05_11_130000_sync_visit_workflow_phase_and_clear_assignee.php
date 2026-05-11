<?php

use App\Models\Visit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align care_delivery_workflow with current_phase and assignee rules:
     * - Visits claimed by a staff member must not sit on a team workflow bucket.
     * - Team workflow rows must have no assignee; current_phase matches workflow target.
     */
    public function up(): void
    {
        DB::table('visits')
            ->whereNotNull('assigned_staff_id')
            ->update(['care_delivery_workflow' => null]);

        foreach (Visit::CARE_DELIVERY_TARGET_PHASES as $workflow => $phase) {
            DB::table('visits')
                ->where('care_delivery_workflow', $workflow)
                ->update(['current_phase' => $phase]);
        }

        DB::table('visits')
            ->whereNotNull('care_delivery_workflow')
            ->update([
                'assigned_staff_id' => null,
                'assigned_at' => null,
            ]);
    }

    public function down(): void
    {
        // Non-reversible data repair migration.
    }
};
