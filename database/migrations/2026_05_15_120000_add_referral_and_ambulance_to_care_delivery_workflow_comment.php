<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Documents referral + ambulance in care_delivery_workflow column comment (queue routing).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE visits
            MODIFY care_delivery_workflow VARCHAR(32) NULL
            COMMENT 'Module queue: registration,triage,medical_records,clinical,laboratory,pharmacy,billing,nursing,imaging,ambulance,referral'
            AFTER current_phase
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE visits
            MODIFY care_delivery_workflow VARCHAR(32) NULL
            COMMENT 'Module queue: registration,triage,clinical,laboratory,pharmacy,billing,nursing,imaging'
            AFTER current_phase
        ");
    }
};
