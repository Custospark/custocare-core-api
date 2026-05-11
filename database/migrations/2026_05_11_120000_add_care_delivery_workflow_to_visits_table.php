<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds care_delivery_workflow for module-queue routing (aligned with Custocare Frontend).
 *
 * @see \App\Models\Visit::CARE_DELIVERY_TARGET_PHASES
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('care_delivery_workflow', 32)
                ->nullable()
                ->after('current_phase')
                ->comment('Module queue: registration,triage,clinical,laboratory,pharmacy,billing,nursing,imaging');

            $table->index(
                ['facility_id', 'care_delivery_workflow'],
                'visits_facility_care_delivery_workflow_index'
            );
        });

        // Backfill from current_phase where possible (idempotent for re-run if column cleared)
        DB::statement("
            UPDATE visits
            SET care_delivery_workflow = CASE current_phase
                WHEN 'registration' THEN 'registration'
                WHEN 'waiting_triage' THEN 'triage'
                WHEN 'triage' THEN 'triage'
                WHEN 'waiting_provider' THEN 'clinical'
                WHEN 'consultation' THEN 'clinical'
                WHEN 'diagnostic_tests' THEN 'laboratory'
                WHEN 'awaiting_results' THEN 'laboratory'
                WHEN 'treatment' THEN 'pharmacy'
                WHEN 'billing' THEN 'billing'
                WHEN 'observation' THEN 'nursing'
                WHEN 'procedures' THEN 'imaging'
                ELSE NULL
            END
            WHERE care_delivery_workflow IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_facility_care_delivery_workflow_index');
            $table->dropColumn('care_delivery_workflow');
        });
    }
};
