<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONSULTATION FORM
 * 
 * Tracks specialist consultation requests and responses
 * Manages referral process between healthcare providers
 * Documents consult findings and recommendations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('consultations');
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();

            // ── Core Relations ──────────────────────────────────────────────
            $table->unsignedBigInteger('facility_id')
                  ->comment('FK → facilities.id');
            $table->foreign('facility_id')
                  ->references('id')->on('facilities')
                  ->onDelete('restrict');

            $table->unsignedBigInteger('visit_id')
                  ->comment('FK → visits.id');
            $table->foreign('visit_id')
                  ->references('id')->on('visits')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('patient_id')
                  ->comment('FK → patients.id');
            $table->foreign('patient_id')
                  ->references('id')->on('patients')
                  ->onDelete('restrict');

            // ── Provider Relations ──────────────────────────────────────────
            $table->unsignedBigInteger('requesting_staff_id')
                  ->comment('FK → staff.id (clinician requesting consult)');
            $table->foreign('requesting_staff_id')
                  ->references('id')->on('staff')
                  ->onDelete('restrict');
            
            $table->unsignedBigInteger('consultant_staff_id')
                  ->nullable()
                  ->comment('FK → staff.id (specialist providing consult)');
            $table->foreign('consultant_staff_id')
                  ->references('id')->on('staff')
                  ->onDelete('set null');

            // ── Consultation Details ────────────────────────────────────────
            $table->string('specialty_required', 200)
                  ->comment('Specialty needed for consultation');
            
            $table->enum('consultation_type', ['in_person', 'telemedicine', 'urgent', 'elective', 'emergency'])
                  ->default('in_person')
                  ->comment('Type of consultation');
            
            $table->enum('priority', ['routine', 'urgent', 'emergent'])
                  ->default('routine')
                  ->comment('Consultation priority level');
            
            $table->text('clinical_question')
                  ->comment('Specific question or reason for consultation');
            
            $table->text('background_information')
                  ->nullable()
                  ->comment('Relevant history, previous workup, current medications');
            
            $table->json('attached_documents')
                  ->nullable()
                  ->comment('Array of document IDs or file paths');

            // ── Consultation Response ───────────────────────────────────────
            $table->text('findings')
                  ->nullable()
                  ->comment('Consultant\'s clinical findings');
            
            $table->text('recommendations')
                  ->nullable()
                  ->comment('Consultant\'s recommendations and plan');
            
            $table->json('recommended_orders')
                  ->nullable()
                  ->comment('{"labs": [], "imaging": [], "medications": [], "procedures": []}');
            
            $table->text('consultant_notes')
                  ->nullable()
                  ->comment('Additional notes from consultant');

            // ── Workflow Status ─────────────────────────────────────────────
            $table->enum('request_status', ['pending', 'accepted', 'declined', 'completed', 'cancelled'])
                  ->default('pending')
                  ->comment('Status of consultation request');
            
            $table->timestamp('requested_at')
                  ->useCurrent()
                  ->comment('When consultation was requested');
            
            $table->timestamp('responded_at')
                  ->nullable()
                  ->comment('When consultant responded');
            
            $table->timestamp('completed_at')
                  ->nullable()
                  ->comment('When consultation was completed');
            
            $table->text('decline_reason')
                  ->nullable()
                  ->comment('Reason if consultation was declined');
            
            $table->text('cancellation_reason')
                  ->nullable()
                  ->comment('Reason if consultation was cancelled');

            // ── Scheduling ──────────────────────────────────────────────────
            $table->timestamp('scheduled_for')
                  ->nullable()
                  ->comment('Scheduled consultation date/time');
            
            $table->integer('duration_minutes')
                  ->default(30)
                  ->comment('Expected consultation duration');
            
            $table->string('location', 200)
                  ->nullable()
                  ->comment('Room or virtual meeting link');

            // ── Follow-up ───────────────────────────────────────────────────
            $table->boolean('requires_followup')
                  ->default(false)
                  ->comment('Whether follow-up consultation is needed');
            
            $table->timestamp('followup_by')
                  ->nullable()
                  ->comment('Recommended follow-up date');
            
            $table->text('followup_instructions')
                  ->nullable()
                  ->comment('Specific follow-up instructions');

            // ── Custom Fields ───────────────────────────────────────────────
            $table->json('custom_fields')
                  ->nullable()
                  ->comment('Facility-specific consultation fields');
            
            $table->json('satisfaction_metrics')
                  ->nullable()
                  ->comment('Post-consultation satisfaction scores if collected');

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────────────
            $table->index('facility_id');
            $table->index('visit_id');
            $table->index('patient_id');
            $table->index('requesting_staff_id');
            $table->index('consultant_staff_id');
            $table->index('specialty_required');
            $table->index('request_status');
            $table->index('priority');
            $table->index('consultation_type');
            $table->index('requested_at');
            $table->index(['patient_id', 'request_status']);
            $table->index(['visit_id', 'specialty_required']);
            $table->index(['requesting_staff_id', 'request_status']);
            $table->index(['consultant_staff_id', 'request_status']);
            $table->index(['request_status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};