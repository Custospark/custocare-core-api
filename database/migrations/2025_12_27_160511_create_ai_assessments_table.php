<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * AI_ASSESSMENTS - AI/ML clinical decision support records
     * Regulatory Compliance: FDA 510(k), EU MDR for AI/ML medical devices
     */
    public function up(): void
    {
        Schema::create('ai_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('assessment_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('clinical_encounter_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // AI model identification
            $table->string('ai_model_name', 200);
            $table->string('ai_model_version', 50)->index();
            $table->string('ai_model_vendor', 200)->nullable();
            $table->enum('model_type', [
                'diagnostic_assistant',
                'risk_stratification',
                'treatment_recommendation',
                'drug_interaction_checker',
                'image_analysis',
                'clinical_decision_support',
                'predictive_analytics',
                'natural_language_processing',
                'triage_assistant'
            ])->index();
            
            // Regulatory compliance
            $table->boolean('is_fda_cleared')->default(false);
            $table->string('fda_clearance_number', 100)->nullable();
            $table->boolean('is_ce_marked')->default(false);
            $table->string('ce_marking_number', 100)->nullable();
            $table->enum('regulatory_classification', [
                'class_i_medical_device',
                'class_ii_medical_device',
                'class_iii_medical_device',
                'non_medical_device',
                'wellness_tool'
            ])->nullable();
            
            // Input data
            $table->json('input_features')->comment('Input parameters provided to AI');
            $table->string('input_features_hash', 128)->comment('SHA-256 for reproducibility');
            $table->json('input_data_sources')->nullable()->comment('Where input data came from');
            
            // AI output
            $table->json('output_predictions')->comment('Raw AI model output');
            $table->json('output_confidence_scores')->comment('Confidence levels for predictions');
            $table->json('recommendations')->comment('Actionable recommendations');
            $table->json('risk_scores')->nullable();
            $table->json('alternative_suggestions')->nullable();
            
            // Explainability (XAI - Explainable AI)
            $table->json('feature_importance')->nullable()->comment('Which features influenced decision');
            $table->text('explanation_text')->nullable()->comment('Human-readable explanation');
            $table->json('supporting_evidence')->nullable()->comment('Clinical evidence links');
            
            // Human review & validation
            $table->enum('human_review_status', [
                'pending_review',
                'accepted',
                'modified',
                'rejected',
                'overridden',
                'not_applicable'
            ])->default('pending_review')->index();
            
            $table->unsignedBigInteger('reviewed_by_staff_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->json('modifications_made')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Clinical action taken
            $table->boolean('recommendation_implemented')->nullable();
            $table->json('actions_taken')->nullable();
            $table->text('reason_not_implemented')->nullable();
            
            // Performance tracking (for model improvement)
            $table->boolean('clinical_outcome_recorded')->default(false);
            $table->json('actual_outcome')->nullable();
            $table->decimal('prediction_accuracy', 5, 4)->nullable()->comment('Post-hoc accuracy');
            
            // Safety & monitoring
            $table->boolean('adverse_event_flagged')->default(false);
            $table->text('adverse_event_description')->nullable();
            $table->json('safety_alerts')->nullable();
            
            // Processing metadata
            $table->unsignedSmallInteger('processing_time_ms')->nullable();
            $table->string('processing_server', 100)->nullable();
            $table->timestamp('assessed_at')->index();
            
            // Audit trail
            $table->timestamps();
            $table->json('metadata')->nullable();
            $table->softDeletes(); // Added for data retention compliance
            
            // Foreign keys
            $table->foreign('clinical_encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('reviewed_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['facility_id', 'assessed_at']);
            $table->index(['ai_model_name', 'ai_model_version', 'assessed_at']);
            $table->index(['human_review_status', 'assessed_at']);
            $table->index(['patient_id', 'assessed_at']);
            $table->index(['created_at', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assessments');
    }
};