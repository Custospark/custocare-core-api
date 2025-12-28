<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a materialized view-like table for real-time visit status tracking
     * Note: This is updated via CDC from visit_events
     */
    public function up(): void
    {
        Schema::create('visit_current_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Current location & phase
            $table->unsignedBigInteger('current_department_id')->nullable()->index();
            $table->enum('current_phase', [
                'registration', 'waiting_triage', 'triage', 'waiting_provider',
                'consultation', 'diagnostic_tests', 'awaiting_results', 'treatment',
                'procedures', 'observation', 'billing', 'discharge_pending', 'discharged'
            ])->index();
            
            // Wait time tracking
            $table->timestamp('waiting_since')->nullable()->index();
            $table->unsignedSmallInteger('total_wait_minutes')->nullable();
            $table->unsignedSmallInteger('current_phase_duration_minutes')->nullable();
            
            // Next action
            $table->timestamp('next_scheduled_action_at')->nullable()->index();
            $table->string('next_action_type', 100)->nullable();
            $table->json('pending_tasks')->nullable();
            $table->unsignedTinyInteger('pending_tasks_count')->default(0);
            
            // Critical alerts
            $table->json('critical_alerts')->nullable();
            $table->boolean('has_critical_alerts')->default(false)->index();
            $table->unsignedTinyInteger('acuity_score')->index();
            
            // Staff assignment
            $table->json('staff_assigned_ids')->nullable();
            $table->unsignedBigInteger('primary_provider_staff_id')->nullable()->index();
            $table->unsignedBigInteger('primary_nurse_staff_id')->nullable();
            
            // Clinical snapshot
            $table->json('recent_vitals_last_reading')->nullable();
            $table->timestamp('vitals_last_recorded_at')->nullable();
            $table->json('active_orders')->nullable();
            $table->unsignedTinyInteger('active_orders_count')->default(0);
            
            // Estimated completion
            $table->timestamp('estimated_completion_time')->nullable();
            $table->unsignedSmallInteger('estimated_minutes_remaining')->nullable();
            
            // Update tracking
            $table->timestamp('last_event_at')->index();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->timestamp('materialized_at')->index();
            
            // Composite indexes for optimized queries
            $table->index(['current_department_id', 'waiting_since', 'acuity_score']);
            $table->index(['facility_id', 'current_phase', 'waiting_since']);
            $table->index(['has_critical_alerts', 'acuity_score']);
            
            // Foreign key constraints (if relationships exist in database)
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('current_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('primary_provider_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('primary_nurse_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            $table->timestamps();
        });
        
        // Add comment explaining CDC nature
        DB::statement("COMMENT ON TABLE visit_current_states IS 'Materialized view for real-time visit status tracking. Updated via CDC from visit_events. Used for real-time dashboards and queue management.'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_current_states');
    }
};