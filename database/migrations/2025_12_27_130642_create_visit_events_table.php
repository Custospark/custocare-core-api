<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            
            // Event classification
            $table->enum('event_type', [
                'visit_created',
                'patient_arrived',
                'patient_registered',
                'triage_started',
                'triage_completed',
                'vitals_recorded',
                'routed_to_department',
                'provider_assigned',
                'consultation_started',
                'consultation_completed',
                'diagnostic_ordered',
                'diagnostic_completed',
                'medication_ordered',
                'medication_administered',
                'procedure_started',
                'procedure_completed',
                'condition_changed',
                'admission_ordered',
                'transfer_initiated',
                'discharge_ordered',
                'discharge_completed',
                'visit_cancelled',
                'patient_left_ama',
                'patient_lwbs',
                'clinical_note_added',
                'billing_updated',
                'insurance_verified',
                'consent_obtained',
                'alert_triggered',
                'escalation_required'
            ])->index();
            
            // Event payload (schema-versioned JSON)
            $table->json('event_payload')->comment('Schema version + event-specific data');
            $table->string('payload_schema_version', 20)->default('1.0');
            
            // Actor information
            $table->enum('actor_type', ['staff', 'patient', 'system', 'device', 'external_system'])->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_identifier', 200)->nullable()->comment('For external systems/devices');
            
            // Context
            $table->unsignedBigInteger('department_id_at_time')->nullable();
            $table->string('system_component', 100)->nullable()->comment('Which system generated event');
            $table->string('client_ip', 45)->nullable();
            $table->string('client_user_agent', 512)->nullable();
            
            // Event chain (for verification)
            $table->unsignedBigInteger('preceding_event_id')->nullable()->index();
            $table->string('integrity_hash', 128)->comment('SHA-256 hash of event + preceding_hash');
            
            // Timing
            $table->timestamp('event_occurred_at')->index();
            $table->timestamp('event_recorded_at')->index();
            $table->unsignedSmallInteger('processing_latency_ms')->nullable();
            
            // Audit (immutable - no updates/deletes)
            $table->timestamp('created_at');
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('preceding_event_id')->references('id')->on('visit_events')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['visit_id', 'event_occurred_at']);
            $table->index(['facility_id', 'event_type', 'event_occurred_at']);
            $table->index(['event_type', 'event_occurred_at']);
            $table->index(['actor_type', 'actor_id', 'event_occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};