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
        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->uuid('encounter_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Encounter classification
            $table->enum('encounter_type', [
                'initial_consultation',
                'followup_consultation',
                'procedure',
                'diagnostic_review',
                'medication_review',
                'telehealth_visit',
                'specialist_consultation',
                'pre_operative_assessment',
                'post_operative_followup',
                'discharge_summary'
            ])->index();
            
            // Provider information
            $table->unsignedBigInteger('primary_provider_staff_id')->index();
            $table->unsignedBigInteger('supervising_provider_staff_id')->nullable();
            $table->unsignedBigInteger('department_id')->index();
            
            // SOAP Note Components (Structured Clinical Documentation)
            
            // SUBJECTIVE - Patient-reported information
            $table->text('subjective_assessment')->nullable()->comment('Chief complaint, HPI, ROS');
            $table->json('chief_complaints')->nullable();
            $table->text('history_present_illness')->nullable();
            $table->json('review_of_systems')->nullable();
            $table->text('patient_concerns')->nullable();
            
            // OBJECTIVE - Clinician observations
            $table->text('objective_findings')->nullable()->comment('Physical exam, vitals, labs');
            $table->json('vital_signs')->nullable()->comment('BP, HR, RR, Temp, O2, Pain');
            $table->json('physical_examination')->nullable()->comment('Structured by body system');
            $table->json('laboratory_results')->nullable();
            $table->json('imaging_results')->nullable();
            $table->json('diagnostic_test_results')->nullable();
            
            // ASSESSMENT - Clinical judgment
            $table->json('assessment_diagnosis_codes')->comment('ICD-10 codes with descriptions');
            $table->text('clinical_impression')->nullable();
            $table->json('differential_diagnoses')->nullable();
            $table->unsignedTinyInteger('severity_score')->nullable()->comment('1-10 scale');
            $table->json('risk_factors')->nullable();
            $table->json('comorbidities')->nullable();
            
            // PLAN - Treatment plan
            $table->json('plan_treatment_codes')->nullable()->comment('CPT codes for planned treatments');
            $table->text('treatment_plan')->nullable();
            $table->json('medications_prescribed')->nullable();
            $table->json('procedures_planned')->nullable();
            $table->json('referrals_ordered')->nullable();
            $table->json('followup_instructions')->nullable();
            $table->timestamp('next_review_scheduled_at')->nullable();
            
            // Additional clinical notes
            $table->json('clinical_notes_structured')->nullable()->comment('Additional structured data');
            $table->text('clinical_notes_free_text')->nullable();
            $table->text('provider_comments')->nullable();
            
            // Risk flags & alerts
            $table->json('risk_flags')->nullable()->comment('Fall risk, suicide risk, abuse, etc.');
            $table->json('safety_alerts')->nullable();
            $table->boolean('requires_immediate_attention')->default(false);
            
            // Quality metrics
            $table->boolean('meets_quality_measures')->nullable();
            $table->json('quality_measure_codes')->nullable()->comment('HEDIS, PQRS measures');
            
            // Clinical decision support
            $table->boolean('ai_assistance_used')->default(false);
            $table->json('clinical_decision_support_alerts')->nullable();
            
            // Documentation status
            $table->enum('documentation_status', [
                'in_progress',
                'completed',
                'signed',
                'amended',
                'corrected',
                'entered_in_error'
            ])->default('in_progress')->index();
            
            $table->timestamp('documented_at')->index();
            $table->timestamp('signed_at')->nullable();
            $table->string('electronic_signature_hash', 128)->nullable();
            
            // Amendments & corrections
            $table->unsignedBigInteger('amended_from_encounter_id')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestamp('amended_at')->nullable();
            
            // Billing linkage
            $table->boolean('is_billable')->default(true);
            $table->string('billing_code', 20)->nullable()->comment('E&M level code');
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('primary_provider_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('supervising_provider_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict');
            $table->foreign('amended_from_encounter_id')->references('id')->on('clinical_encounters')->onDelete('set null');
            $table->foreign('created_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Composite indexes for common queries
            $table->index(['facility_id', 'visit_id']);
            $table->index(['patient_id', 'documented_at']);
            $table->index(['primary_provider_staff_id', 'documented_at'],'provider_id_doc_at_unique');
            $table->index(['documentation_status', 'documented_at']);
            $table->index(['encounter_type', 'documented_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_encounters');
    }
};