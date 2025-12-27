<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * VISIT_ROUTES - Department routing history
     * Purpose: Track patient flow through facility departments
     */
    public function up(): void
    {
        Schema::create('visit_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            
            // Routing details
            $table->unsignedBigInteger('from_department_id')->nullable();
            $table->unsignedBigInteger('to_department_id')->index();
            
            $table->enum('routing_reason', [
                'initial_assignment',
                'specialist_consultation',
                'diagnostic_imaging',
                'laboratory_tests',
                'surgical_procedure',
                'capacity_management',
                'escalation_of_care',
                'de_escalation_of_care',
                'patient_request',
                'admission_to_inpatient',
                'discharge_processing'
            ])->index();
            
            $table->text('routing_notes')->nullable();
            $table->unsignedBigInteger('routing_staff_id')->nullable();
            
            // Queue metrics
            $table->unsignedSmallInteger('queue_position_at_move')->nullable();
            $table->unsignedSmallInteger('estimated_wait_minutes')->nullable();
            $table->unsignedSmallInteger('actual_wait_minutes')->nullable();
            
            // Timing
            $table->timestamp('routed_at')->index();
            $table->timestamp('arrived_at_department')->nullable();
            $table->timestamp('departed_department')->nullable();
            $table->unsignedSmallInteger('actual_transfer_duration_minutes')->nullable();
            
            // Handoff documentation
            $table->text('handoff_summary')->nullable();
            $table->unsignedBigInteger('sending_staff_id')->nullable();
            $table->unsignedBigInteger('receiving_staff_id')->nullable();
            $table->boolean('handoff_acknowledged')->default(false);
            $table->timestamp('handoff_acknowledged_at')->nullable();
            
            // Transport
            $table->enum('transport_method', [
                'ambulatory',
                'wheelchair',
                'stretcher',
                'bed',
                'ambulance'
            ])->nullable();
            $table->boolean('requires_escort')->default(false);
            
            // Audit
            $table->timestamps();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('from_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('to_department_id')->references('id')->on('departments')->onDelete('restrict');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            
            // Performance indexes
            $table->index(['facility_id', 'routed_at']);
            $table->index(['visit_id', 'routed_at']);
            $table->index(['to_department_id', 'routed_at']);
            $table->index(['routing_reason', 'routed_at']);
            
            // Additional indexes for common queries
            $table->index(['handoff_acknowledged', 'routed_at']);
            $table->index(['requires_escort', 'routed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_routes');
    }
};