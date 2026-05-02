<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIAGNOSIS FORM
 * 
 * Structured diagnosis records following ICD/SNOMED standards
 * Supports primary, secondary, and differential diagnoses
 * Tracks diagnosis certainty and clinical status
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('diagnoses');
        Schema::create('diagnoses', function (Blueprint $table) {
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
                  ->comment('FK → staff.id (diagnosing clinician)');
            $table->foreign('staff_id')
                  ->references('id')->on('staff')
                  ->onDelete('restrict');

            // ── Diagnosis Data ──────────────────────────────────────────────
            $table->string('diagnosis_code', 50)
                  ->comment('ICD-10/11 or SNOMED CT code');
            
            $table->string('diagnosis_description', 500)
                  ->comment('Human-readable diagnosis description');
            
            $table->enum('diagnosis_type', ['primary', 'secondary', 'differential', 'admitting', 'discharge', 'provisional'])
                  ->default('primary')
                  ->comment('Type/role of this diagnosis');
            
            $table->enum('certainty', ['confirmed', 'probable', 'possible', 'rule_out', 'suspected', 'uncertain'])
                  ->default('confirmed')
                  ->comment('Diagnostic certainty level');
            
            $table->enum('clinical_status', ['active', 'inactive', 'resolved', 'remission', 'chronic'])
                  ->default('active')
                  ->comment('Current clinical status');
            
            $table->text('clinical_notes')
                  ->nullable()
                  ->comment('Additional clinical notes specific to this diagnosis');
            
            $table->date('onset_date')
                  ->nullable()
                  ->comment('Date of symptom/disease onset');
            
            $table->date('abatement_date')
                  ->nullable()
                  ->comment('Date when condition resolved');

            // ── Supporting Evidence ─────────────────────────────────────────
            $table->json('supporting_evidence')
                  ->nullable()
                  ->comment('{"labs": ["lab_id"], "imaging": ["image_id"], "clinical_findings": []}');
            
            $table->text('diagnostic_criteria_met')
                  ->nullable()
                  ->comment('Specific criteria used to establish diagnosis');

            // ── Custom Fields ───────────────────────────────────────────────
            $table->json('custom_fields')
                  ->nullable()
                  ->comment('Facility-specific diagnosis fields');
            
            $table->json('coding_metadata')
                  ->nullable()
                  ->comment('ICD/SNOMED coding meta: version, mapping confidence, etc.');

            // ── Workflow ────────────────────────────────────────────────────
            $table->enum('verification_status', ['draft', 'verified', 'disputed', 'invalidated'])
                  ->default('draft')
                  ->comment('Verification workflow status');
            
            $table->timestamp('verified_at')->nullable()
                  ->comment('When diagnosis was verified');
            
            $table->unsignedBigInteger('verified_by')->nullable()
                  ->comment('Staff ID who verified the diagnosis');
            
            $table->text('dispute_reason')->nullable()
                  ->comment('Reason if diagnosis is disputed');

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ────────────────────────────────────────────────────
            $table->index('facility_id');
            $table->index('visit_id');
            $table->index('patient_id');
            $table->index('staff_id');
            $table->index('diagnosis_code');
            $table->index('diagnosis_type');
            $table->index('clinical_status');
            $table->index('verification_status');
            $table->index(['patient_id', 'diagnosis_code']);
            $table->index(['visit_id', 'diagnosis_type']);
            $table->index(['diagnosis_code', 'clinical_status']);
            $table->unique(['visit_id', 'diagnosis_code', 'diagnosis_type'], 'unique_diagnosis_per_visit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};