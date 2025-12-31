<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPARTMENT_QUEUE_VIEWS - Real-time department operations dashboard
     * Refresh Strategy: 30-second batch update
     */
    public function up(): void
    {
        Schema::create('department_queue_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('department_id')->index();
            
            // Queue classification
            $table->enum('queue_type', [
                'triage',
                'consultation',
                'procedures',
                'diagnostic_imaging',
                'laboratory',
                'pharmacy',
                'discharge'
            ])->index();
            
            // Current metrics
            $table->unsignedSmallInteger('patients_waiting_count')->default(0);
            $table->unsignedSmallInteger('patients_in_treatment_count')->default(0);
            $table->unsignedSmallInteger('total_active_patients')->default(0);
            
            // Wait time statistics
            $table->unsignedSmallInteger('average_wait_minutes')->nullable();
            $table->unsignedSmallInteger('median_wait_minutes')->nullable();
            $table->unsignedSmallInteger('longest_wait_minutes')->nullable();
            $table->unsignedBigInteger('longest_waiting_visit_id')->nullable();
            
            // Next patients (for staff display)
            $table->json('next_patient_ids')->nullable()->comment('Ordered by priority');
            $table->json('critical_patients')->nullable();
            
            // Staffing
            $table->unsignedTinyInteger('staff_available_count')->default(0);
            $table->unsignedTinyInteger('staff_total_count')->default(0);
            $table->json('available_staff_ids')->nullable();
            
            // Capacity
            $table->unsignedTinyInteger('capacity_percentage')->nullable();
            $table->unsignedTinyInteger('bed_utilization_percentage')->nullable();
            $table->enum('capacity_status', ['normal', 'busy', 'critical', 'at_capacity'])->index();
            
            // Predictions (ML model output)
            $table->json('predicted_wait_times')->nullable();
            $table->timestamp('predicted_next_available_at')->nullable();
            
            // Snapshot metadata
            $table->timestamp('snapshot_at')->index();
            $table->timestamps();
            
            // Foreign keys (assuming facilities and departments tables exist)
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('cascade');
            
            // Unique constraint (one row per department-queue type)
            $table->unique(['department_id', 'queue_type']);
            
            // Performance indexes
            $table->index(['facility_id', 'capacity_status', 'snapshot_at'],'fac_capcity_status_snapshot_unque');
            $table->index(['department_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_queue_views');
    }
};