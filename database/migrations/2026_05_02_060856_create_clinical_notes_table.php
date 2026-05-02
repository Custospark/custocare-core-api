<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLINICAL NOTES
 * 
 * Stores free-text clinical observations, assessments, and plans
 * Captures clinician's narrative notes during patient encounter
 * Supports structured data via custom_fields JSON
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clinical_notes');
        Schema::create('clinical_notes', function (Blueprint $table) {
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

            $table->unsignedBigInteger('staff_id')
                  ->comment('FK → staff.id (clinician creating notes)');
            $table->foreign('staff_id')
                  ->references('id')->on('staff')
                  ->onDelete('restrict');

            // ── Clinical Note Content ───────────────────────────────────────
            $table->text('subjective')
                  ->nullable()
                  ->comment('Chief complaint, HPI, symptoms reported by patient');
            
            $table->text('objective')
                  ->nullable()
                  ->comment('Physical exam findings, observations');
            
            $table->text('assessment')
                  ->nullable()
                  ->comment('Clinical impression, differential diagnoses');
            
            $table->text('plan')
                  ->nullable()
                  ->comment('Treatment plan, medications, follow-up instructions');
            
            $table->text('review_of_systems')
                  ->nullable()
                  ->comment('Systematic review of body systems');
            
            $table->text('past_medical_history')
                  ->nullable()
                  ->comment('Relevant PMH, PSH, allergies, medications');

            // ── Note Metadata ───────────────────────────────────────────────
            $table->enum('note_type', ['initial', 'follow_up', 'progress', 'discharge', 'consultation'])
                  ->default('progress')
                  ->comment('Type of clinical note');
            
            $table->enum('note_status', ['draft', 'final', 'amended', 'cancelled'])
                  ->default('draft')
                  ->comment('Workflow status of the note');
            
            $table->timestamp('noted_at')
                  ->useCurrent()
                  ->comment('Date/time when the note pertains to');
            
            $table->text('signature')->nullable()
                  ->comment('Digital signature or authentication token');

            // ── Custom Fields ───────────────────────────────────────────────
            $table->json('custom_fields')
                  ->nullable()
                  ->comment('JSON structure for facility-specific fields: {"field_name": {"type": "string|number|date|boolean|select", "value": "..."}}');
            
            $table->json('structured_data')
                  ->nullable()
                  ->comment('Machine-readable structured data extracted from notes');

            // ── Revision Tracking ──────────────────────────────────────────
            $table->unsignedBigInteger('parent_note_id')
                  ->nullable()
                  ->comment('FK for amended notes, points to previous version');
            $table->foreign('parent_note_id')
                  ->references('id')->on('clinical_notes')
                  ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────────────
            $table->index('facility_id');
            $table->index('visit_id');
            $table->index('patient_id');
            $table->index('staff_id');
            $table->index('note_type');
            $table->index('note_status');
            $table->index('noted_at');
            $table->index(['patient_id', 'noted_at']);
            $table->index(['visit_id', 'note_type']);
            $table->fullText(['subjective', 'objective', 'assessment', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};