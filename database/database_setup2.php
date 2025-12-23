<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Global Healthcare Platform Database Schema
     * 
     * Core Principles:
     * 1. Multi-cluster strategy: Identity (global), Operations (sharded), Reference (cached)
     * 2. CQRS pattern: Write-optimized normalized tables + Read-optimized materialized views
     * 3. Compliance-first: HIPAA, GDPR, data residency built into schema
     * 4. Scale-ready: Sharding by facility_id for operational data
     * 
     * @return void
     */
    public function up(): void
    {
        // ============================================
        // 1. GLOBAL IDENTITY & COMPLIANCE CLUSTER
        // ============================================
        
        Schema::create('users', function (Blueprint $table) {
            // Core Identity Columns
            $table->uuid('id')->primary()->comment('Global unique identifier');
            $table->string('global_user_uuid', 64)->unique()->comment('Public-facing UUID');
            $table->string('national_id_hash', 128)->unique()->comment('SHA3-512 hash of encrypted national ID');
            $table->enum('entity_type', ['patient', 'staff', 'administrator', 'system'])->default('patient');
            $table->enum('identity_state', ['pending_verification', 'verified', 'suspended', 'deactivated'])->default('pending_verification');
            
            // Contact Information (Encrypted at Application Layer)
            $table->string('email_hash', 128)->nullable()->index()->comment('Hash for lookup, email encrypted separately');
            $table->string('phone_hash', 128)->nullable()->index()->comment('Hash for lookup, phone encrypted separately');
            
            // Data Residency & Compliance
            $table->string('data_residency_region', 8)->default('us-east-1')->comment('AWS region code or equivalent');
            $table->enum('consent_level', ['full', 'treatment_only', 'emergency_only', 'none'])->default('treatment_only');
            $table->json('privacy_flags')->nullable()->comment('GDPR/HIPAA-specific flags');
            
            // Audit & Security
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignUuid('created_from_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->ipAddress('registration_ip')->nullable();
            $table->string('registration_user_agent')->nullable();
            
            // Timestamps with timezone support
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->timestamp('last_activity_at')->nullable();
            
            // Strategic Indexes
            $table->index(['identity_state', 'data_residency_region']);
            $table->index(['entity_type', 'created_at']);
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            
            // Medical Identity
            $table->string('patient_uuid', 64)->unique()->comment('Facility-facing identifier');
            $table->string('medical_record_number_hash', 128)->nullable()->unique()->comment('Hash of MRN');
            
            // Emergency & Contact Chain (Encrypted JSON)
            $table->json('emergency_contact_chain')->nullable()->comment('Encrypted emergency contacts with relationships');
            $table->json('authorized_representatives')->nullable()->comment('Legal guardians, power of attorney');
            
            // Medical Preferences
            $table->json('allergies')->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->json('medication_preferences')->nullable();
            $table->string('blood_type', 3)->nullable();
            $table->json('advance_directives')->nullable()->comment('Living will, DNR status');
            
            // Insurance Information (Encrypted)
            $table->json('insurance_providers')->nullable()->comment('Encrypted insurance details');
            
            // Demographic Data (Compliance Managed)
            $table->date('date_of_birth')->nullable();
            $table->enum('biological_sex', ['male', 'female', 'intersex', 'unknown'])->nullable();
            $table->string('gender_identity', 50)->nullable();
            $table->json('preferred_languages')->nullable();
            
            // Timestamps
            $table->timestampsTz();
            $table->timestamp('last_medical_update_at')->nullable();
            
            // Indexes
            $table->index(['date_of_birth', 'biological_sex']);
            $table->index('last_medical_update_at');
        });

        Schema::create('patient_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            
            // Consent Details
            $table->enum('consent_type', [
                'treatment', 
                'research', 
                'data_sharing', 
                'marketing', 
                'emergency_contact', 
                'billing'
            ]);
            $table->json('scope_facility_ids')->nullable()->comment('Null = all facilities, array = specific');
            $table->json('data_categories')->nullable()->comment('Specific data types covered');
            
            // Legal Framework
            $table->enum('legal_basis', ['consent', 'contract', 'legal_obligation', 'vital_interest', 'public_interest'])->default('consent');
            $table->string('document_version_hash', 64)->nullable()->comment('Hash of consent document');
            $table->string('consent_form_id', 128)->nullable()->comment('External form identifier');
            
            // Status & Timing
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'revoked', 'expired', 'suspended'])->default('active');
            
            // Witness & Verification
            $table->foreignUuid('staff_witness_id')->nullable()->constrained('staff');
            $table->string('witness_signature_hash', 128)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('geolocation')->nullable()->comment('Approximate location at consent');
            
            // Audit Trail
            $table->json('modification_history')->nullable();
            
            $table->timestampsTz();
            
            // Unique constraint: one active consent per type per patient
            $table->unique(['patient_id', 'consent_type', 'status'], 'unique_active_consent');
            $table->index(['patient_id', 'status', 'expires_at']);
            $table->index(['consent_type', 'granted_at']);
        });

        // ============================================
        // 2. FACILITY & STAFF DOMAIN
        // ============================================

        Schema::create('facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('global_facility_code', 32)->unique();
            
            // Facility Details
            $table->string('name', 255);
            $table->enum('facility_type', [
                'hospital', 
                'clinic', 
                'pharmacy', 
                'lab', 
                'specialty_center', 
                'virtual_care'
            ]);
            $table->enum('tier', ['primary', 'secondary', 'tertiary', 'quaternary']);
            
            // Location & Contact
            $table->json('address')->nullable()->comment('Encrypted address details');
            $table->json('contact_channels')->nullable()->comment('Phone, email, telemedicine links');
            $table->string('timezone', 50)->default('UTC');
            
            // Operational Capacity
            $table->integer('bed_count')->nullable();
            $table->integer('operating_rooms')->nullable();
            $table->json('specialties')->nullable();
            $table->json('accreditations')->nullable();
            
            // Business & Legal
            $table->string('license_number', 100)->nullable();
            $table->string('tax_id_hash', 128)->nullable();
            $table->json('insurance_contracts')->nullable();
            
            // Data Management
            $table->string('data_residency_region', 8);
            $table->json('compliance_config')->nullable()->comment('Facility-specific compliance rules');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended', 'decommissioned'])->default('active');
            
            $table->timestampsTz();
            $table->softDeletesTz();
            
            $table->index(['facility_type', 'status']);
            $table->index(['data_residency_region', 'created_at']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            
            // Professional Identity
            $table->string('professional_license_number', 100)->nullable();
            $table->string('national_provider_identifier', 10)->nullable()->unique();
            $table->json('specialization_codes')->nullable()->comment('Array of specialty codes');
            
            // Employment Status
            $table->enum('employment_status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active');
            $table->enum('global_role_level', [
                'practitioner', 
                'supervisor', 
                'department_head', 
                'facility_admin', 
                'system_admin'
            ])->default('practitioner');
            
            // Qualifications
            $table->json('degrees_certifications')->nullable();
            $table->json('languages_spoken')->nullable();
            
            // Operational Constraints
            $table->json('schedule_template')->nullable()->comment('Default schedule pattern');
            $table->integer('max_patients_per_day')->nullable();
            
            // Compliance
            $table->timestamp('background_check_expires_at')->nullable();
            $table->timestamp('training_certified_at')->nullable();
            
            $table->timestampsTz();
            $table->timestamp('last_credential_verified_at')->nullable();
            
            $table->index(['employment_status', 'global_role_level']);
            $table->index(['specialization_codes', 'created_at']);
        });

        Schema::create('staff_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            
            // Credential Details
            $table->enum('credential_type', [
                'medical_license', 
                'board_certification', 
                'training_certificate', 
                'accreditation', 
                'privilege'
            ]);
            $table->string('credential_code', 100);
            $table->string('issuing_authority', 255);
            $table->string('issuing_country', 2)->nullable();
            
            // Validity Period
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            
            // Verification Status
            $table->enum('verification_status', [
                'pending', 
                'verified', 
                'expired', 
                'revoked', 
                'suspended'
            ])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('verified_by_staff_id')->nullable()->constrained('staff');
            
            // Evidence
            $table->string('document_hash', 128)->nullable()->comment('Hash of uploaded document');
            $table->json('verification_metadata')->nullable();
            
            // Snapshot for Audit
            $table->timestamp('snapshot_taken_at')->useCurrent();
            $table->json('credential_snapshot')->nullable()->comment('Full credential data at time of snapshot');
            
            $table->timestampsTz();
            
            $table->unique(['staff_id', 'credential_type', 'credential_code', 'valid_from'], 'unique_credential_version');
            $table->index(['staff_id', 'verification_status', 'valid_to']);
            $table->index(['credential_type', 'valid_to', 'verification_status']);
        });

        Schema::create('facility_staff_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            
            // Role Assignment
            $table->enum('role_code', [
                'physician', 
                'nurse', 
                'technician', 
                'pharmacist', 
                'receptionist', 
                'billing_specialist',
                'administrator'
            ]);
            $table->json('department_ids')->nullable()->comment('Array of department UUIDs');
            
            // Privileges (Bitmask for performance)
            $table->bigInteger('privileges_bitmask')->default(0)->comment('64-bit privilege mask');
            $table->json('privileges_detail')->nullable()->comment('Detailed privilege definitions');
            
            // Schedule & Availability
            $table->json('shift_schedule')->nullable()->comment('Recurring schedule pattern');
            $table->json('working_hours')->nullable()->comment('Weekly hours configuration');
            
            // Effective Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->comment('Null = ongoing assignment');
            
            // Status
            $table->enum('assignment_status', ['active', 'pending', 'suspended', 'ended'])->default('active');
            $table->foreignUuid('assigned_by_staff_id')->nullable()->constrained('staff');
            
            $table->timestampsTz();
            
            // Unique active role per facility-staff combination
            $table->unique(['facility_id', 'staff_id', 'role_code', 'assignment_status'], 'unique_active_assignment');
            $table->index(['facility_id', 'role_code', 'assignment_status']);
            $table->index(['staff_id', 'effective_from', 'effective_to']);
        });

        // ============================================
        // 3. VISIT DOMAIN (SHARDED CLUSTER)
        // ============================================

        Schema::create('visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard Key: (facility_id, created_at::date)
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('patient_id')->constrained('patients');
            
            // Visit Identification
            $table->string('visit_uuid', 64)->unique()->comment('Public-facing visit identifier');
            $table->enum('visit_type', [
                'outpatient', 
                'inpatient', 
                'emergency', 
                'virtual', 
                'home_health', 
                'follow_up'
            ]);
            
            // Triage & Priority
            $table->integer('acuity_score')->nullable()->comment('1-5 scale, 1=most urgent');
            $table->enum('priority', ['immediate', 'emergent', 'urgent', 'semi-urgent', 'non-urgent'])->default('non-urgent');
            
            // Referral Chain
            $table->foreignUuid('referring_facility_id')->nullable()->constrained('facilities');
            $table->foreignUuid('referring_staff_id')->nullable()->constrained('staff');
            $table->string('external_referral_id', 100)->nullable();
            
            // Insurance & Financial
            $table->string('insurance_preauth_id', 100)->nullable();
            $table->enum('payment_type', ['insurance', 'self_pay', 'government', 'charity'])->nullable();
            
            // Clinical Context
            $table->json('chief_complaint')->nullable();
            $table->json('symptoms_on_arrival')->nullable();
            $table->json('vitals_on_arrival')->nullable();
            
            // Operational State
            $table->enum('status', [
                'scheduled', 
                'checked_in', 
                'triaged', 
                'in_progress', 
                'admitted', 
                'discharged', 
                'cancelled', 
                'no_show'
            ])->default('scheduled');
            
            $table->foreignUuid('current_department_id')->nullable()->constrained('departments');
            $table->enum('current_phase', [
                'registration', 
                'triage', 
                'consultation', 
                'procedures', 
                'observation', 
                'billing', 
                'discharge'
            ])->nullable();
            
            // Timing
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamp('clinical_started_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->timestamp('expected_discharge_at')->nullable();
            
            // Metadata
            $table->json('arrival_metadata')->nullable()->comment('Mode of arrival, accompanying person, etc.');
            $table->json('tags')->nullable()->comment('Custom tags for workflow management');
            
            // Audit
            $table->foreignUuid('created_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('last_state_change_at')->useCurrent();
            
            $table->timestampsTz();
            
            // Strategic Indexes for Sharding
            $table->index(['facility_id', 'created_at']); // Shard key prefix
            $table->index(['facility_id', 'status', 'current_department_id']);
            $table->index(['patient_id', 'created_at']);
            $table->index(['facility_id', 'discharged_at', 'status']);
            $table->index(['acuity_score', 'created_at'])->where('status', 'in', ['checked_in', 'triaged']);
        });

        Schema::create('visit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            
            // Shard co-location with visits
            $table->foreignUuid('facility_id')->constrained('facilities');
            
            // Event Details
            $table->enum('event_type', [
                'created',
                'checked_in',
                'triaged',
                'routed',
                'clinical_started',
                'procedure_started',
                'procedure_completed',
                'medication_administered',
                'lab_ordered',
                'lab_result_received',
                'admitted',
                'discharge_ordered',
                'discharge_completed',
                'billing_finalized',
                'cancelled'
            ]);
            
            $table->enum('event_source', ['staff', 'system', 'patient', 'device', 'integration'])->default('staff');
            
            // Actor Information
            $table->uuid('actor_id')->nullable()->comment('Could be staff_id, patient_id, or system component');
            $table->enum('actor_type', ['staff', 'patient', 'system', 'device'])->nullable();
            
            // Context at Time of Event
            $table->foreignUuid('department_id_at_time')->nullable()->constrained('departments');
            $table->foreignUuid('assigned_staff_id_at_time')->nullable()->constrained('staff');
            
            // Event Data
            $table->json('payload')->nullable()->comment('Schema-versioned event data');
            $table->string('payload_schema_version', 10)->default('1.0');
            
            // Chain Integrity
            $table->uuid('preceding_event_id')->nullable();
            $table->string('integrity_hash', 128)->nullable()->comment('SHA3-512 of previous_hash + current_event');
            
            // Audit
            $table->ipAddress('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            
            $table->timestampsTz(3); // Millisecond precision
            
            // Optimized Indexes
            $table->index(['visit_id', 'created_at']);
            $table->index(['facility_id', 'event_type', 'created_at']);
            $table->index(['actor_id', 'actor_type', 'created_at']);
            $table->index(['department_id_at_time', 'created_at']);
        });

        Schema::create('visit_actors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff');
            
            // Role Snapshot
            $table->enum('participation_type', [
                'attending',
                'consulting',
                'assisting',
                'supervising',
                'documenting',
                'billing',
                'coordinating'
            ]);
            
            $table->json('role_details')->nullable()->comment('Snapshot of role at time of assignment');
            $table->foreignUuid('credential_snapshot_id')->nullable()->constrained('staff_credentials');
            
            // Department Context
            $table->foreignUuid('department_id')->nullable()->constrained('departments');
            
            // Time Tracking
            $table->timestamp('involvement_started_at')->useCurrent();
            $table->timestamp('involvement_ended_at')->nullable();
            $table->integer('time_involvement_minutes')->nullable()->comment('Calculated on closure');
            
            // Responsibility Flags
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_billing_provider')->default(false);
            $table->json('responsibility_areas')->nullable();
            
            $table->timestampsTz();
            
            // Unique constraint: staff can only have one active role per visit
            $table->unique(['visit_id', 'staff_id', 'involvement_ended_at'], 'unique_active_participation');
            $table->index(['staff_id', 'involvement_started_at']);
            $table->index(['facility_id', 'department_id', 'involvement_started_at']);
        });

        Schema::create('visit_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            
            // Routing Details
            $table->foreignUuid('from_department_id')->constrained('departments');
            $table->foreignUuid('to_department_id')->constrained('departments');
            
            $table->enum('routing_reason', [
                'specialist_consultation',
                'equipment_required',
                'capacity_overflow',
                'procedure_required',
                'observation_needed',
                'discharge_process'
            ]);
            
            // Queue Management
            $table->integer('queue_position_at_move')->nullable();
            $table->integer('estimated_wait_minutes')->nullable();
            $table->integer('actual_wait_minutes')->nullable()->comment('Calculated after move');
            
            // Execution
            $table->foreignUuid('routing_staff_id')->nullable()->constrained('staff');
            $table->timestamp('routed_at')->useCurrent();
            $table->timestamp('arrived_at')->nullable();
            $table->integer('transfer_duration_seconds')->nullable()->comment('Calculated after arrival');
            
            // Notes
            $table->text('routing_notes')->nullable();
            
            $table->timestampsTz();
            
            $table->index(['visit_id', 'routed_at']);
            $table->index(['facility_id', 'to_department_id', 'routed_at']);
            $table->index(['from_department_id', 'to_department_id', 'created_at']);
        });

        // ============================================
        // 4. CLINICAL DOMAIN
        // ============================================

        Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            
            // Encounter Details
            $table->enum('encounter_type', [
                'initial_consultation',
                'follow_up',
                'procedure',
                'rounds',
                'telemedicine',
                'nursing_assessment'
            ]);
            
            // SOAP Note Structure
            $table->json('subjective_assessment')->nullable()->comment('Patient-reported symptoms, history');
            $table->json('objective_findings')->nullable()->comment('Clinician observations, vitals, exam');
            $table->json('assessment_diagnosis_codes')->nullable()->comment('ICD-10, SNOMED codes');
            $table->json('plan_treatment_codes')->nullable()->comment('CPT, HCPCS codes');
            
            // Clinical Documentation
            $table->json('clinical_notes_structured')->nullable()->comment('Structured clinical data');
            $table->text('clinical_notes_free_text')->nullable()->comment('Free text notes');
            
            // Severity & Risk
            $table->integer('severity_score')->nullable();
            $table->json('risk_flags')->nullable();
            $table->json('alerts')->nullable()->comment('Drug interactions, allergies, precautions');
            
            // Timing
            $table->timestamp('encounter_started_at')->useCurrent();
            $table->timestamp('encounter_ended_at')->nullable();
            $table->timestamp('next_review_scheduled_at')->nullable();
            
            // Responsible Clinicians
            $table->foreignUuid('attending_staff_id')->constrained('staff');
            $table->json('contributing_staff_ids')->nullable();
            
            // Compliance
            $table->boolean('cosign_required')->default(false);
            $table->foreignUuid('cosigned_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('cosigned_at')->nullable();
            
            // Versioning
            $table->integer('version')->default(1);
            $table->uuid('previous_version_id')->nullable();
            
            $table->timestampsTz();
            
            $table->index(['visit_id', 'encounter_started_at']);
            $table->index(['attending_staff_id', 'encounter_started_at']);
            $table->index(['facility_id', 'encounter_type', 'created_at']);
        });

        Schema::create('ai_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('clinical_encounter_id')->constrained('clinical_encounters')->cascadeOnDelete();
            
            // AI Model Details
            $table->string('ai_model_name', 100);
            $table->string('ai_model_version', 50);
            $table->string('ai_provider', 100)->nullable()->comment('OpenAI, Google, custom, etc.');
            
            // Input/Output
            $table->json('input_features')->nullable()->comment('Features sent to AI model');
            $table->string('input_features_hash', 128)->nullable()->comment('For idempotency');
            
            $table->json('output_raw')->nullable()->comment('Raw AI response');
            $table->json('output_processed')->nullable()->comment('Processed recommendations');
            $table->json('confidence_scores')->nullable();
            
            // Clinical Integration
            $table->json('recommendations')->nullable();
            $table->json('differential_diagnosis')->nullable();
            $table->json('treatment_suggestions')->nullable();
            
            // Human Review
            $table->enum('human_review_status', [
                'pending_review',
                'accepted',
                'accepted_with_modifications',
                'rejected',
                'deferred'
            ])->default('pending_review');
            
            $table->foreignUuid('reviewed_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            // Regulatory Compliance
            $table->boolean('fda_cleared')->default(false);
            $table->boolean('ce_marked')->default(false);
            $table->json('regulatory_warnings')->nullable();
            $table->json('explainability_data')->nullable()->comment('For audit and transparency');
            
            // Performance Tracking
            $table->decimal('inference_time_ms', 10, 2)->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            
            $table->timestampsTz();
            
            $table->index(['clinical_encounter_id', 'created_at']);
            $table->index(['ai_model_name', 'created_at']);
            $table->index(['human_review_status', 'reviewed_at']);
        });

        // ============================================
        // 5. SERVICE & BILLING DOMAIN
        // ============================================

        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Regional Sharding
            $table->string('region_code', 8)->default('global');
            
            // Service Identification
            $table->string('service_code', 50)->comment('CPT, ICD, HCPCS, or custom');
            $table->enum('coding_system', ['cpt', 'icd10', 'hcpcs', 'snomed', 'local', 'custom'])->default('local');
            
            // Service Details
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('service_category', [
                'consultation',
                'procedure',
                'diagnostic',
                'therapeutic',
                'pharmaceutical',
                'device',
                'administrative'
            ]);
            
            // Clinical Requirements
            $table->json('minimum_required_credentials')->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('contraindications')->nullable();
            
            // Operational Details
            $table->integer('default_duration_minutes')->nullable();
            $table->json('required_equipment')->nullable();
            $table->json('possible_complications')->nullable();
            
            // Regulatory
            $table->json('regulatory_approval_status')->nullable();
            $table->json('insurance_coverage_rules')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'deprecated', 'pending_approval'])->default('active');
            
            $table->timestampsTz();
            
            $table->unique(['service_code', 'coding_system', 'region_code']);
            $table->index(['service_category', 'status']);
            $table->index(['region_code', 'created_at']);
        });

        Schema::create('service_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_catalog_id')->constrained('service_catalogs')->cascadeOnDelete();
            
            // Version Details
            $table->integer('version_number')->default(1);
            $table->string('version_notes', 500)->nullable();
            
            // Pricing
            $table->decimal('base_price_amount', 15, 4);
            $table->string('currency', 3)->default('USD');
            $table->decimal('facility_markup_percentage', 5, 2)->nullable()->default(0);
            
            // Insurance
            $table->decimal('insurance_coverage_percentage', 5, 2)->nullable();
            $table->boolean('requires_preauthorization')->default(false);
            $table->json('preauthorization_rules')->nullable();
            
            // Validity Period
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            
            // Audit
            $table->string('version_hash', 128)->nullable()->comment('Hash of all version data');
            $table->foreignUuid('approved_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestampsTz();
            
            $table->unique(['service_catalog_id', 'version_number']);
            $table->index(['effective_from', 'effective_to']);
            $table->index(['service_catalog_id', 'effective_to'])->where('effective_to IS NOT NULL');
        });

        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits')->cascadeOnDelete();
            
            // Cycle Definition
            $table->enum('cycle_type', [
                'admission',
                'daily',
                'weekly',
                'procedure',
                'pharmacy',
                'device',
                'miscellaneous'
            ]);
            
            // Period
            $table->timestamp('period_start');
            $table->timestamp('period_end')->nullable();
            
            // Status
            $table->enum('billing_status', [
                'draft',
                'pending_review',
                'generated',
                'sent_to_insurance',
                'insurance_pending',
                'patient_pending',
                'partially_paid',
                'fully_paid',
                'disputed',
                'written_off'
            ])->default('draft');
            
            // Financials
            $table->decimal('total_amount_charged', 15, 4)->default(0);
            $table->decimal('insurance_covered_amount', 15, 4)->default(0);
            $table->decimal('patient_responsibility_amount', 15, 4)->default(0);
            $table->decimal('discount_applied', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            
            // Insurance Details
            $table->string('insurance_claim_id', 100)->nullable();
            $table->json('insurance_response')->nullable();
            
            // Dates
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_to_insurance_at')->nullable();
            $table->timestamp('insurance_processed_at')->nullable();
            $table->timestamp('patient_notified_at')->nullable();
            $table->timestamp('due_date')->nullable();
            
            // Responsible Party
            $table->foreignUuid('billing_staff_id')->nullable()->constrained('staff');
            
            $table->timestampsTz();
            
            $table->index(['visit_id', 'cycle_type', 'period_start']);
            $table->index(['facility_id', 'billing_status', 'due_date']);
            $table->index(['insurance_claim_id', 'created_at']);
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_cycle_id')->constrained('billing_cycles')->cascadeOnDelete();
            
            // Service Reference
            $table->foreignUuid('service_version_id')->nullable()->constrained('service_versions');
            $table->json('service_version_snapshot')->nullable()->comment('Frozen snapshot at time of service');
            
            // Clinical Context
            $table->foreignUuid('clinical_encounter_id')->nullable()->constrained('clinical_encounters');
            $table->foreignUuid('performed_by_staff_id')->nullable()->constrained('staff');
            $table->foreignUuid('department_performed_id')->nullable()->constrained('departments');
            
            // Quantity & Pricing
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price_at_time', 15, 4);
            $table->decimal('total_price', 15, 4)->storedAs('quantity * unit_price_at_time');
            $table->decimal('discount_percentage', 5, 2)->default(0);
            
            // Medical Necessity
            $table->text('medical_necessity_notes')->nullable();
            $table->json('supporting_documentation')->nullable();
            
            // Audit
            $table->string('audit_trail_hash', 128)->nullable();
            $table->foreignUuid('verified_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('verified_at')->nullable();
            
            $table->timestampsTz();
            
            $table->index(['billing_cycle_id', 'clinical_encounter_id']);
            $table->index(['performed_by_staff_id', 'created_at']);
            $table->index(['service_version_snapshot', 'created_at']);
        });

        // ============================================
        // 6. INVENTORY & PHARMACY
        // ============================================

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities');
            
            // Item Identification
            $table->string('item_code', 100)->comment('NDC, UPC, or facility code');
            $table->enum('item_type', [
                'medication',
                'supply',
                'device',
                'equipment',
                'consumable'
            ]);
            
            // Details
            $table->string('name', 255);
            $table->string('generic_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('categories')->nullable();
            
            // Specifications
            $table->string('unit_of_measure', 50)->default('each');
            $table->decimal('unit_weight_grams', 10, 4)->nullable();
            $table->decimal('unit_volume_ml', 10, 4)->nullable();
            $table->json('storage_requirements')->nullable();
            
            // Regulatory
            $table->string('ndc_number', 20)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->json('regulatory_classifications')->nullable();
            
            // Status
            $table->enum('status', ['active', 'discontinued', 'recalled', 'pending'])->default('active');
            
            $table->timestampsTz();
            $table->softDeletesTz();
            
            $table->unique(['facility_id', 'item_code']);
            $table->index(['item_type', 'status']);
            $table->index(['facility_id', 'categories']);
        });

        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items');
            
            // Transaction Details
            $table->enum('transaction_type', [
                'purchase',
                'receive_transfer',
                'adjustment',
                'consumption',
                'waste',
                'expiry',
                'return',
                'theft'
            ]);
            
            $table->enum('transaction_cause', [
                'manual',
                'system',
                'reconciliation',
                'automated_restock',
                'cycle_count'
            ])->default('manual');
            
            // Quantity Management
            $table->decimal('quantity_change', 12, 4)->comment('Positive = in, Negative = out');
            $table->decimal('quantity_before', 12, 4);
            $table->decimal('quantity_after', 12, 4)->storedAs('quantity_before + quantity_change');
            
            // Pricing
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            
            // References
            $table->foreignUuid('reference_visit_id')->nullable()->constrained('visits');
            $table->foreignUuid('reference_prescription_id')->nullable()->constrained('prescriptions');
            $table->foreignUuid('reference_order_id')->nullable()->comment('Purchase order ID');
            
            // Batch & Expiry
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            
            // Verification
            $table->foreignUuid('verified_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            $table->timestampsTz();
            
            // Comprehensive indexes for inventory queries
            $table->index(['facility_id', 'inventory_item_id', 'created_at']);
            $table->index(['transaction_type', 'created_at']);
            $table->index(['batch_number', 'expiry_date']);
            $table->index(['reference_visit_id', 'created_at']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits');
            $table->foreignUuid('clinical_encounter_id')->nullable()->constrained('clinical_encounters');
            
            // Prescription Details
            $table->json('medication_details')->comment('Drug name, strength, form');
            $table->json('sig')->comment('Signatura: instructions for use');
            
            // Dosage
            $table->decimal('quantity_prescribed', 12, 4);
            $table->string('quantity_unit', 50);
            $table->integer('refills_allowed')->default(0);
            $table->integer('days_supply')->nullable();
            
            // Timing
            $table->timestamp('prescribed_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('Prescription expiry');
            
            // Prescriber
            $table->foreignUuid('prescriber_staff_id')->constrained('staff');
            $table->json('prescriber_credentials_snapshot')->nullable();
            
            // Status
            $table->enum('status', [
                'draft',
                'active',
                'sent_to_pharmacy',
                'in_process',
                'partially_filled',
                'filled',
                'cancelled',
                'expired'
            ])->default('draft');
            
            // Pharmacy Information
            $table->foreignUuid('pharmacy_facility_id')->nullable()->constrained('facilities');
            $table->string('external_pharmacy_id', 100)->nullable();
            
            // Safety Checks
            $table->json('drug_interaction_check')->nullable();
            $table->json('allergy_check')->nullable();
            $table->boolean('patient_educated')->default(false);
            
            $table->timestampsTz();
            
            $table->index(['visit_id', 'prescribed_at']);
            $table->index(['prescriber_staff_id', 'status', 'created_at']);
            $table->index(['facility_id', 'status', 'expires_at']);
        });

        Schema::create('medication_dispenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard co-location
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('visit_id')->constrained('visits');
            $table->foreignUuid('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            
            // Dispense Details
            $table->json('prescription_snapshot')->comment('Frozen prescription at time of dispense');
            $table->decimal('quantity_dispensed', 12, 4);
            $table->string('lot_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            
            // Inventory Link
            $table->foreignUuid('inventory_ledger_id')->nullable()->constrained('inventory_ledger');
            
            // Staff Verification (4-Eyes Principle)
            $table->foreignUuid('dispensed_by_staff_id')->constrained('staff');
            $table->foreignUuid('checked_by_staff_id')->nullable()->constrained('staff');
            $table->timestamp('checked_at')->nullable();
            
            // Patient Education
            $table->boolean('patient_education_provided')->default(false);
            $table->json('education_materials_given')->nullable();
            $table->text('followup_instructions')->nullable();
            
            // Regulatory Compliance
            $table->json('regulatory_compliance_flags')->nullable();
            $table->json('controlled_substance_checks')->nullable();
            
            // Status
            $table->enum('dispense_status', [
                'prepared',
                'verified',
                'dispensed',
                'cancelled',
                'returned'
            ])->default('prepared');
            
            $table->timestamp('dispensed_at')->nullable();
            
            $table->timestampsTz();
            
            $table->index(['prescription_id', 'dispensed_at']);
            $table->index(['dispensed_by_staff_id', 'created_at']);
            $table->index(['facility_id', 'dispense_status', 'created_at']);
        });

        // ============================================
        // 7. READ MODELS (CQRS OPTIMIZED)
        // ============================================

        Schema::create('visit_current_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            
            // Current Operational State
            $table->foreignUuid('current_department_id')->nullable()->constrained('departments');
            $table->enum('current_phase', [
                'registration',
                'triage',
                'consultation',
                'procedures',
                'observation',
                'billing',
                'discharge'
            ]);
            
            // Timing
            $table->timestamp('current_phase_started_at')->useCurrent();
            $table->timestamp('next_scheduled_action_at')->nullable();
            $table->timestamp('waiting_since')->nullable();
            $table->integer('waiting_duration_minutes')->storedAs(
                "EXTRACT(EPOCH FROM (COALESCE(next_scheduled_action_at, NOW()) - waiting_since)) / 60"
            )->nullable();
            
            // Queue Information
            $table->integer('queue_position')->nullable();
            $table->integer('estimated_wait_minutes')->nullable();
            
            // Clinical Status
            $table->json('recent_vitals')->nullable();
            $table->timestamp('last_vitals_at')->nullable();
            $table->json('active_medications')->nullable();
            $table->json('pending_orders')->nullable();
            
            // Staff Assignments
            $table->json('assigned_staff_ids')->nullable();
            $table->uuid('primary_attending_staff_id')->nullable();
            
            // Alerts
            $table->json('critical_alerts')->nullable();
            $table->json('pending_tasks')->nullable();
            $table->integer('pending_tasks_count')->default(0);
            
            // Performance Metrics
            $table->decimal('acuity_score', 3, 1)->nullable();
            $table->integer('total_encounters_count')->default(0);
            
            // Estimated Completion
            $table->timestamp('estimated_completion_time')->nullable();
            
            // Refresh Tracking
            $table->timestamp('materialized_at')->useCurrent();
            $table->timestamp('last_event_processed_at')->nullable();
            
            $table->timestampsTz();
            
            // Optimized for dashboard queries
            $table->index(['current_department_id', 'current_phase', 'waiting_since']);
            $table->index(['acuity_score', 'waiting_since']);
            $table->index(['primary_attending_staff_id', 'current_phase']);
            $table->index(['materialized_at', 'visit_id']);
        });

        Schema::create('department_queue_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities');
            $table->foreignUuid('department_id')->constrained('departments');
            
            // Queue Metrics
            $table->enum('queue_type', [
                'triage',
                'consultation',
                'procedures',
                'pharmacy',
                'discharge',
                'billing'
            ]);
            
            // Counts
            $table->integer('patients_waiting_count')->default(0);
            $table->integer('patients_in_progress_count')->default(0);
            $table->integer('patients_completed_today')->default(0);
            
            // Wait Times
            $table->integer('average_wait_minutes')->nullable();
            $table->integer('longest_wait_minutes')->nullable();
            $table->integer('ninety_percentile_wait_minutes')->nullable();
            
            // Capacity
            $table->integer('staff_available_count')->default(0);
            $table->integer('staff_assigned_count')->default(0);
            $table->decimal('capacity_percentage', 5, 2)->nullable();
            
            // Next Patients
            $table->json('next_patient_ids')->nullable();
            $table->json('high_acuity_patient_ids')->nullable();
            
            // Predictive Analytics
            $table->json('predicted_wait_times')->nullable();
            $table->timestamp('prediction_generated_at')->nullable();
            
            // Performance
            $table->json('performance_metrics')->nullable()->comment('SLAs, throughput, etc.');
            
            // Refresh Metadata
            $table->timestamp('calculated_at')->useCurrent();
            $table->integer('calculation_duration_ms')->nullable();
            
            $table->timestampsTz();
            
            // Real-time dashboard indexes
            $table->unique(['facility_id', 'department_id', 'queue_type']);
            $table->index(['facility_id', 'patients_waiting_count']);
            $table->index(['queue_type', 'calculated_at']);
        });

        Schema::create('patient_visit_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            
            // Active Visits
            $table->json('active_visit_ids')->nullable();
            $table->integer('active_visits_count')->default(0);
            
            // Recent History
            $table->json('recent_visits_last_30_days')->nullable();
            $table->json('recent_visits_last_90_days')->nullable();
            $table->integer('total_visits_count')->default(0);
            
            // Appointments
            $table->json('upcoming_appointments')->nullable();
            $table->json('past_appointments_last_30_days')->nullable();
            
            // Clinical Summary
            $table->json('active_prescriptions')->nullable();
            $table->json('pending_prescriptions')->nullable();
            $table->json('allergies')->nullable();
            $table->json('chronic_conditions')->nullable();
            $table->json('recent_diagnoses')->nullable();
            
            // Financial
            $table->decimal('outstanding_bills_total', 15, 4)->default(0);
            $table->json('outstanding_bills_detail')->nullable();
            $table->decimal('paid_last_30_days', 15, 4)->default(0);
            
            // Health Metrics
            $table->json('health_metrics_trends')->nullable();
            $table->json('recent_vitals_summary')->nullable();
            $table->json('preventive_care_due')->nullable();
            
            // Care Team
            $table->json('care_team_members')->nullable();
            $table->json('preferred_providers')->nullable();
            
            // Consent Status
            $table->json('active_consents')->nullable();
            $table->json('consent_expirations_next_30_days')->nullable();
            
            // Refresh Tracking
            $table->timestamp('summarized_at')->useCurrent();
            $table->timestamp('last_visit_update_at')->nullable();
            $table->timestamp('last_clinical_update_at')->nullable();
            
            $table->timestampsTz();
            
            // Patient portal optimized indexes
            $table->index(['active_visits_count', 'summarized_at']);
            $table->index(['outstanding_bills_total', 'patient_id']);
        });

        // ============================================
        // 8. GOVERNANCE & AUDIT
        // ============================================

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Shard Key: (entity_type, created_at::date)
            $table->enum('entity_type', [
                'user',
                'patient',
                'staff',
                'visit',
                'clinical_encounter',
                'prescription',
                'billing_cycle',
                'inventory_item'
            ]);
            
            $table->uuid('entity_id');
            
            // Operation Details
            $table->enum('operation', [
                'create',
                'read',
                'update',
                'delete',
                'export',
                'access',
                'consent_change',
                'override'
            ]);
            
            // Actor Information
            $table->enum('performed_by_type', ['staff', 'patient', 'system', 'integration', 'admin']);
            $table->uuid('performed_by_id');
            $table->foreignUuid('performed_by_staff_id')->nullable()->constrained('staff');
            
            // Change Details
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            
            // Context
            $table->foreignUuid('facility_id')->nullable()->constrained('facilities');
            $table->foreignUuid('visit_id')->nullable()->constrained('visits');
            
            // Compliance
            $table->enum('compliance_reason', [
                'treatment',
                'billing',
                'audit',
                'research',
                'legal',
                'quality_improvement',
                'operational'
            ])->default('treatment');
            
            $table->boolean('legal_hold_flag')->default(false);
            $table->json('compliance_metadata')->nullable();
            
            // Technical Details
            $table->string('request_id', 100)->nullable()->comment('Correlation ID');
            $table->ipAddress('user_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('geolocation')->nullable();
            $table->string('endpoint', 500)->nullable();
            
            $table->timestampsTz(3); // Millisecond precision
            
            // Immutable audit trail indexes
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['performed_by_type', 'performed_by_id', 'created_at']);
            $table->index(['facility_id', 'operation', 'created_at']);
            $table->index(['compliance_reason', 'created_at']);
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('data_residency_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Rule Scope
            $table->string('region_code', 8);
            $table->enum('data_category', [
                'clinical',
                'financial',
                'identity',
                'audit',
                'research',
                'operational'
            ]);
            
            // Storage Rules
            $table->json('allowed_storage_regions')->nullable();
            $table->json('processing_allowed_regions')->nullable();
            $table->json('backup_allowed_regions')->nullable();
            
            // Encryption Requirements
            $table->enum('encryption_at_rest', ['aes256', 'aes512', 'custom', 'none'])->default('aes256');
            $table->enum('encryption_in_transit', ['tls1.3', 'mutual_tls', 'vpn', 'none'])->default('tls1.3');
            $table->json('key_management_rules')->nullable();
            
            // Retention
            $table->integer('retention_period_years');
            $table->json('archiving_rules')->nullable();
            $table->json('destruction_protocol')->nullable();
            
            // Cross-Border
            $table->boolean('cross_border_transfer_allowed')->default(false);
            $table->json('cross_border_conditions')->nullable();
            $table->boolean('transfer_approval_required')->default(true);
            
            // Legal Framework
            $table->json('applicable_regulations')->nullable()->comment('HIPAA, GDPR, etc.');
            $table->json('legal_contacts')->nullable();
            
            // Status
            $table->enum('status', ['active', 'draft', 'suspended', 'superseded'])->default('active');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            
            $table->timestampsTz();
            
            $table->unique(['region_code', 'data_category', 'status'], 'unique_active_rule');
            $table->index(['data_category', 'effective_from', 'effective_to']);
        });

        // ============================================
        // 9. SUPPORTING TABLES
        // ============================================

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            
            $table->string('name', 255);
            $table->string('code', 50);
            $table->enum('department_type', [
                'emergency',
                'outpatient',
                'inpatient',
                'icu',
                'or',
                'radiology',
                'laboratory',
                'pharmacy',
                'administration'
            ]);
            
            $table->json('services_offered')->nullable();
            $table->json('operating_hours')->nullable();
            $table->integer('bed_count')->nullable();
            $table->integer('chair_count')->nullable();
            
            $table->json('contact_information')->nullable();
            $table->foreignUuid('department_head_staff_id')->nullable()->constrained('staff');
            
            $table->enum('status', ['active', 'inactive', 'renovation', 'closed'])->default('active');
            
            $table->timestampsTz();
            
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'department_type', 'status']);
        });

        Schema::create('facility_service_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignUuid('service_catalog_id')->constrained('service_catalogs')->cascadeOnDelete();
            
            $table->foreignUuid('department_id')->nullable()->constrained('departments');
            $table->json('allowed_staff_roles')->nullable();
            $table->json('equipment_required')->nullable();
            
            $table->decimal('facility_specific_price', 15, 4)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->json('approval_workflow')->nullable();
            
            $table->enum('status', ['available', 'unavailable', 'requires_special_approval'])->default('available');
            
            $table->timestampsTz();
            
            $table->unique(['facility_id', 'service_catalog_id', 'department_id']);
            $table->index(['facility_id', 'status']);
        });

        // ============================================
        // 10. PARTITIONING & SHARDING SETUP
        // ============================================

        // Note: Actual partitioning would be database-specific
        // This is conceptual for the migration file
        
        DB::statement("
            COMMENT ON TABLE visits IS 
            'PARTITION BY RANGE (created_at);
             SUBPARTITION BY HASH (facility_id);
             -- Monthly partitions with facility-based subpartitions
            ';
        ");
        
        DB::statement("
            COMMENT ON TABLE audit_logs IS 
            'PARTITION BY RANGE (created_at);
             -- Monthly partitions for efficient retention policy enforcement
            ';
        ");
        
        DB::statement("
            COMMENT ON TABLE inventory_ledger IS 
            'PARTITION BY HASH (facility_id);
             -- Facility-based partitioning for operational isolation
            ';
        ");

        // ============================================
        // 11. COMPLEX INDEXES & CONSTRAINTS
        // ============================================

        // Adding composite indexes for critical query patterns
        Schema::table('visits', function (Blueprint $table) {
            // Covering index for queue management
            $table->index(['current_department_id', 'status', 'current_phase', 'created_at'], 
                        'idx_visits_queue_management');
            
            // Covering index for patient history
            $table->index(['patient_id', 'created_at', 'status', 'visit_type'], 
                        'idx_visits_patient_history');
            
            // Index for acuity-based prioritization
            $table->index(['facility_id', 'acuity_score', 'waiting_since', 'status'], 
                        'idx_visits_acuity_priority')
                  ->where('status', 'in', ['checked_in', 'triaged', 'in_progress']);
        });

        Schema::table('clinical_encounters', function (Blueprint $table) {
            // Covering index for clinician workload
            $table->index(['attending_staff_id', 'encounter_started_at', 'encounter_type'], 
                        'idx_clinical_workload');
            
            // Index for diagnosis-based queries
            $table->index(['assessment_diagnosis_codes', 'encounter_started_at'], 
                        'idx_diagnosis_trends')
                  ->algorithm('gin');
        });

        Schema::table('inventory_ledger', function (Blueprint $table) {
            // Covering index for stock level queries
            $table->index(['inventory_item_id', 'created_at', 'quantity_after'], 
                        'idx_inventory_stock_levels');
            
            // Index for expiry management
            $table->index(['expiry_date', 'quantity_after', 'facility_id'], 
                        'idx_inventory_expiry_management')
                  ->where('quantity_after', '>', 0);
        });

        // ============================================
        // 12. VIEWS FOR COMMON QUERIES
        // ============================================

        DB::statement("
            CREATE OR REPLACE VIEW vw_active_prescriptions AS
            SELECT 
                p.*,
                pat.patient_uuid,
                v.visit_uuid,
                s.professional_license_number as prescriber_license,
                f.name as facility_name,
                CASE 
                    WHEN p.expires_at < NOW() THEN 'expired'
                    WHEN p.status = 'active' AND p.expires_at > NOW() THEN 'active'
                    ELSE p.status
                END as effective_status
            FROM prescriptions p
            JOIN visits v ON p.visit_id = v.id
            JOIN patients pat ON v.patient_id = pat.id
            JOIN staff s ON p.prescriber_staff_id = s.id
            JOIN facilities f ON p.facility_id = f.id
            WHERE p.status IN ('active', 'sent_to_pharmacy', 'in_process')
        ");

        DB::statement("
            CREATE OR REPLACE VIEW vw_department_performance AS
            SELECT 
                d.id as department_id,
                d.name as department_name,
                f.name as facility_name,
                COUNT(DISTINCT v.id) as visit_count_last_7_days,
                AVG(EXTRACT(EPOCH FROM (v.discharged_at - v.checked_in_at))/3600) as avg_length_of_stay_hours,
                AVG(vs.waiting_duration_minutes) as avg_wait_time_minutes,
                COUNT(DISTINCT vsa.staff_id) as staff_count,
                COUNT(DISTINCT CASE WHEN v.status = 'discharged' THEN v.id END) as completed_visits
            FROM departments d
            JOIN facilities f ON d.facility_id = f.id
            LEFT JOIN visits v ON d.id = v.current_department_id 
                AND v.created_at >= NOW() - INTERVAL '7 days'
            LEFT JOIN visit_current_states vs ON v.id = vs.visit_id
            LEFT JOIN visit_actors vsa ON v.id = vsa.visit_id 
                AND vsa.department_id = d.id
            WHERE d.status = 'active'
            GROUP BY d.id, d.name, f.name
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop views first
        DB::statement('DROP VIEW IF EXISTS vw_active_prescriptions');
        DB::statement('DROP VIEW IF EXISTS vw_department_performance');
        
        // Drop tables in reverse order of dependencies
        Schema::dropIfExists('data_residency_rules');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('patient_visit_summaries');
        Schema::dropIfExists('department_queue_views');
        Schema::dropIfExists('visit_current_states');
        Schema::dropIfExists('medication_dispenses');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('inventory_ledger');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('billing_cycles');
        Schema::dropIfExists('service_versions');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('facility_service_mappings');
        Schema::dropIfExists('ai_assessments');
        Schema::dropIfExists('clinical_encounters');
        Schema::dropIfExists('visit_routes');
        Schema::dropIfExists('visit_actors');
        Schema::dropIfExists('visit_events');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facility_staff_roles');
        Schema::dropIfExists('staff_credentials');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('users');
    }
};