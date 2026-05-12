<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PATIENTS - Medical identity and health profile
     * Shard Strategy: Co-located with user_id (same shard as users)
     * Compliance: HIPAA PHI protection, consent-based access
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->uuid('patient_uuid')->unique()->index()->comment('Facility-facing public ID(patient Number)');
            $table->unsignedBigInteger('user_id');
            
            // Medical record identification
            $table->string('medical_record_number_hash', 128)->unique()->comment('Hospital MRN hash');
            $table->string('medical_record_number_encrypted', 512);
            $table->string('previous_mrn_list_encrypted', 2048)->nullable()->comment('For merged records');
            
            // Demographics (critical for clinical decisions)
            $table->date('date_of_birth')->index();
            $table->enum('biological_sex', ['male', 'female', 'intersex', 'unknown'])->comment('Clinical sex for medical decisions');
            $table->enum('gender_identity', ['male', 'female', 'non_binary', 'prefer_not_to_say', 'other'])->nullable();
            $table->string('blood_type', 5)->nullable()->index();
            $table->string('ethnicity', 100)->nullable();
            $table->json('genetic_markers')->nullable()->comment('Relevant genetic information');
            
            // Emergency contacts (encrypted JSONB structure)
            $table->json('emergency_contact_chain_encrypted')->nullable()->comment('Prioritized list of emergency contacts');
            
            // Clinical flags
            $table->json('known_allergies')->nullable()->comment('Drug, food, environmental allergies');
            $table->json('chronic_conditions')->nullable()->comment('ICD-10 codes for chronic diseases');
            $table->json('active_medications')->nullable()->comment('Current medication list');
            $table->boolean('is_organ_donor')->default(false);
            $table->json('advance_directives')->nullable()->comment('DNR, living will, etc.');
            
            // Risk stratification
            $table->unsignedTinyInteger('acuity_baseline')->default(1)->comment('1=Low, 5=Critical');
            $table->json('risk_factors')->nullable()->comment('Fall risk, infection risk, etc.');
            $table->boolean('requires_isolation')->default(false);
            $table->string('isolation_type', 50)->nullable()->comment('Contact, droplet, airborne');
            
            // Consent & privacy
            $table->enum('default_consent_level', ['full', 'restricted', 'minimal', 'none'])->default('full');
            $table->json('privacy_flags')->nullable()->comment('GDPR: right_to_erasure_requested, data_portability, etc.');
            $table->boolean('research_participation_allowed')->default(false);
            $table->boolean('data_sharing_allowed')->default(false);
            
            // Insurance & financial
            $table->string('primary_insurance_provider', 200)->nullable();
            $table->string('primary_insurance_id_encrypted', 512)->nullable();
            $table->string('secondary_insurance_provider', 200)->nullable();
            $table->string('secondary_insurance_id_encrypted', 512)->nullable();
            $table->enum('payment_responsibility', ['self_pay', 'insurance', 'government', 'charity'])->default('self_pay');
            
            // Care coordination
            $table->unsignedBigInteger('primary_care_provider_staff_id')->nullable();
            $table->unsignedBigInteger('primary_care_facility_id')->nullable();
            $table->timestamp('last_wellness_visit_at')->nullable();
            $table->timestamp('next_scheduled_appointment_at')->nullable();
            
            // Patient portal
            $table->boolean('portal_access_enabled')->default(true);
            $table->timestamp('portal_terms_accepted_at')->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->string('preferred_communication_method', 20)->default('email')->comment('email, sms, phone, postal');
            
            // Status tracking
            $table->enum('status', ['active', 'inactive', 'deceased', 'merged', 'test_patient','system_patient'])->default('active')->index();
            $table->timestamp('deceased_at')->nullable();
            $table->unsignedBigInteger('merged_into_patient_id')->nullable()->comment('If duplicate record');
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Performance indexes
            $table->index(['status', 'primary_care_facility_id']);
            $table->index(['date_of_birth', 'biological_sex']);
            $table->index(['last_wellness_visit_at', 'status']);
            $table->index('primary_care_provider_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};