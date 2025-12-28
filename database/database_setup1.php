<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Global-Scale Healthcare Platform Database Migration
 * 
 * Architecture: Multi-cluster, CQRS-optimized, HIPAA/GDPR-compliant
 * Design Philosophy: Event-sourced, audit-first, performance-optimized
 * Scalability: Shard-ready, partition-aware, globally distributed
 * 
 * Created with 8 decades of healthcare database architecture expertise
 * 
 * @version 1.0.0
 * @author Senior Healthcare Database Architect
 * @license Proprietary - Enterprise Healthcare Systems
 */
return new class extends Migration
{
    /**
     * Run the migrations - Deploy complete healthcare infrastructure
     *
     * @return void
     */
    public function up()
    {
        // Set appropriate database configurations for healthcare workloads
        DB::statement('SET SESSION sql_mode = "STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"');
        
        /**
         * ========================================================================
         * CLUSTER 1: GLOBAL IDENTITY REGISTRY (Read-Heavy, Eventually Consistent)
         * ========================================================================
         * Purpose: Centralized patient/staff identity with global consent tracking
         * Replication: Multi-region with eventual consistency
         * Compliance: GDPR Article 7, HIPAA Privacy Rule
         */
        
        // ===== CORE IDENTITY TABLES =====
        
        /**DONE++
         * USERS - Root identity table (Global Identity Anchor)
         * Shard Strategy: Hash(national_id_hash) for global distribution
         * Security: Encrypted at rest (national_id, contact_info)
         * DONE
         */
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('global_user_uuid')->unique()->index();
            $table->string('national_id_hash', 128)->unique()->comment('SHA-256 hashed national ID for privacy');
            $table->string('national_id_encrypted', 512)->comment('AES-256 encrypted national ID');
            $table->string('national_id_country_code', 3)->index();
            $table->string('national_id_country_code', 3)->index();
            $table->string('national_id_country_code', 3)->index();
            
            // Identity verification
            $table->enum('identity_state', ['pending', 'verified', 'suspended', 'archived'])->default('pending')->index();
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('identity_verification_method', 50)->nullable()->comment('passport, biometric, government_id, etc.');
            $table->unsignedBigInteger('identity_verified_by_staff_id')->nullable();
            
            // Data residency & compliance
            $table->string('data_residency_region', 10)->index()->comment('EU, US, APAC, etc.');
            $table->json('allowed_processing_regions')->nullable()->comment('Regions where data can be processed');
            $table->unsignedBigInteger('created_from_facility_id')->nullable()->comment('First touchpoint facility');
            
            // Contact information (encrypted)
            $table->string('email_encrypted', 512)->nullable();
            $table->string('email_hash', 128)->nullable()->index();
            $table->string('phone_encrypted', 512)->nullable();
            $table->string('phone_hash', 128)->nullable()->index();
            //Profile.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('display_name')->nullable(); // optional
            
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            
            $table->json('metadata')->nullable(); // flexible extension
            
            // Account management
            $table->string('password_hash', 255)->nullable()->comment('For patient portal access');
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('requires_password_change')->default(false);
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_secret_encrypted', 512)->nullable();
            
            // Session & security tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_login_user_agent', 512)->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('account_locked_until')->nullable();
            
            // Audit metadata
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->json('metadata')->nullable()->comment('Flexible extension point');
            
            // Performance indexes
            $table->index(['identity_state', 'data_residency_region']);
            $table->index(['created_at', 'identity_state']);
            $table->index('created_from_facility_id');
        });

        /**DONE++
         * PATIENTS - Medical identity and health profile
         * Shard Strategy: Co-located with user_id (same shard as users)
         * Compliance: HIPAA PHI protection, consent-based access
         * DONE*/
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->uuid('patient_uuid')->unique()->index()->comment('Facility-facing public ID');
            $table->unsignedBigInteger('user_id')->unique();
            
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
            $table->json('privacy_flags')->comment('GDPR: right_to_erasure_requested, data_portability, etc.');
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
            $table->enum('status', ['active', 'inactive', 'deceased', 'merged', 'test_patient'])->default('active')->index();
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

        /**DONE++
         * PATIENT_CONSENTS - Legal record of consent/authorization
         * Shard Strategy: Sharded by patient_id
         * Compliance: GDPR Article 7, HIPAA Authorization, 21 CFR Part 11
         */
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('consent_uuid')->unique()->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Consent classification
            $table->enum('consent_type', [
                'treatment',           // General treatment authorization
                'procedures',          // Specific procedure consent
                'anesthesia',         // Anesthesia administration
                'blood_transfusion',  // Blood product consent
                'research',           // Clinical trial participation
                'data_sharing',       // EHR data sharing
                'marketing',          // Marketing communications
                'photography',        // Clinical photography
                'teaching',           // Teaching hospital participation
                'organ_donation',     // Organ/tissue donation
                'release_of_info'     // Information release to third parties
            ])->index();
            
            // Scope definition
            $table->json('scope_facility_ids')->nullable()->comment('NULL = all facilities');
            $table->json('scope_department_ids')->nullable();
            $table->json('scope_staff_ids')->nullable()->comment('Specific providers only');
            $table->json('scope_service_categories')->nullable();
            $table->text('scope_limitations')->nullable()->comment('Free-text limitations');
            
            // Legal basis (GDPR compliance)
            $table->enum('legal_basis', [
                'explicit_consent',    // GDPR Article 6(1)(a)
                'contractual',        // GDPR Article 6(1)(b)
                'legal_obligation',   // GDPR Article 6(1)(c)
                'vital_interests',    // GDPR Article 6(1)(d)
                'legitimate_interest' // GDPR Article 6(1)(f)
            ])->default('explicit_consent');
            
            // Consent lifecycle
            $table->timestamp('granted_at')->index();
            $table->timestamp('effective_from')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->text('revocation_reason')->nullable();
            $table->unsignedBigInteger('revoked_by_staff_id')->nullable();
            
            // Witness & verification
            $table->unsignedBigInteger('witnessed_by_staff_id')->nullable();
            $table->string('witness_signature_hash', 128)->nullable();
            $table->string('patient_signature_hash', 128)->nullable();
            $table->string('signature_method', 50)->nullable()->comment('digital, wet_signature, verbal, implied');
            
            // Digital consent tracking
            $table->string('consent_ip_address', 45)->nullable();
            $table->text('consent_user_agent')->nullable();
            $table->string('consent_device_fingerprint', 128)->nullable();
            $table->string('consent_geolocation', 100)->nullable();
            
            // Document management
            $table->string('consent_form_version', 20)->index();
            $table->string('consent_document_hash', 128)->comment('SHA-256 of signed document');
            $table->string('consent_document_storage_path', 512)->nullable();
            $table->json('consent_document_metadata')->nullable();
            
            // Language & capacity
            $table->string('consent_language', 10)->default('en');
            $table->boolean('interpreter_used')->default(false);
            $table->string('interpreter_language', 50)->nullable();
            $table->boolean('capacity_confirmed')->default(true);
            $table->unsignedBigInteger('legal_guardian_id')->nullable()->comment('If patient lacks capacity');
            
            // Audit & compliance
            $table->enum('status', ['active', 'expired', 'revoked', 'superseded'])->default('active')->index();
            $table->unsignedBigInteger('superseded_by_consent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->json('audit_trail')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            
            // Composite indexes for common queries
            $table->index(['patient_id', 'consent_type', 'status']);
            $table->index(['patient_id', 'effective_from', 'expires_at']);
            $table->index(['status', 'expires_at']); // For cleanup jobs
            $table->index(['granted_at', 'consent_type']);
        });

        /**
         * ========================================================================
         * STAFF & PRACTITIONER REGISTRY
         * ========================================================================
         */
        
        /**DONE++
         * STAFF - Healthcare practitioner registry
         * Shard Strategy: Co-located with user_id
         * Compliance: State licensing verification, credentialing requirements
         * DONE.
         */
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('staff_uuid')->unique()->index();
            $table->unsignedBigInteger('user_id')->unique();
            
            // Professional identification
            $table->string('employee_id', 50)->unique()->index();
            $table->string('professional_title', 100);
            $table->string('professional_license_number_encrypted', 512)->nullable();
            $table->string('professional_license_number_hash', 128)->nullable()->unique();
            $table->string('license_issuing_state', 50)->nullable();
            $table->string('license_issuing_country', 3)->default('USA');
            $table->date('license_expiry_date')->nullable()->index();
            
            // Credentials & certifications
            $table->json('specialization_codes')->comment('NUCC Healthcare Provider Taxonomy codes');
            $table->json('board_certifications')->nullable();
            $table->json('additional_certifications')->nullable();
            $table->string('npi_number', 20)->nullable()->unique()->comment('National Provider Identifier');
            $table->string('dea_number_encrypted', 512)->nullable()->comment('Drug Enforcement Administration');
            $table->date('dea_expiry_date')->nullable();
            
            // Employment status
            $table->enum('employment_status', [
                'active',
                'on_leave',
                'suspended',
                'terminated',
                'retired',
                'credentialing_pending'
            ])->default('credentialing_pending')->index();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'locum_tenens', 'volunteer'])->default('full_time');
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();
            
            // Clinical privileges
            $table->json('clinical_privileges')->nullable()->comment('Procedures/services authorized to perform');
            $table->json('prescribing_authority')->nullable()->comment('Drug schedules allowed to prescribe');
            $table->boolean('can_supervise_trainees')->default(false);
            $table->boolean('can_order_controlled_substances')->default(false);
            $table->boolean('can_sign_death_certificates')->default(false);
            
            // Role & hierarchy
            $table->enum('global_role_level', [
                'super_admin',
                'facility_admin',
                'department_head',
                'attending_physician',
                'fellow',
                'resident',
                'nurse_practitioner',
                'physician_assistant',
                'registered_nurse',
                'licensed_practical_nurse',
                'pharmacist',
                'therapist',
                'technician',
                'support_staff'
            ])->index();
            $table->unsignedBigInteger('reports_to_staff_id')->nullable();
            
            // Availability & scheduling
            $table->json('default_schedule')->nullable()->comment('Weekly availability pattern');
            $table->unsignedSmallInteger('max_concurrent_patients')->default(10);
            $table->unsignedSmallInteger('average_appointment_duration_minutes')->default(30);
            $table->boolean('accepts_new_patients')->default(true);
            
            // Performance & quality metrics
            $table->decimal('patient_satisfaction_score', 3, 2)->nullable()->comment('0.00 to 5.00');
            $table->unsignedInteger('total_patients_treated')->default(0);
            $table->json('quality_metrics')->nullable();
            $table->timestamp('last_peer_review_date')->nullable();
            $table->timestamp('last_competency_assessment_date')->nullable();
            
            // Compliance & safety
            $table->boolean('background_check_completed')->default(false);
            $table->date('background_check_date')->nullable();
            $table->boolean('drug_screening_completed')->default(false);
            $table->date('drug_screening_date')->nullable();
            $table->json('immunization_records')->nullable();
            $table->json('tb_test_records')->nullable();
            $table->boolean('hipaa_training_completed')->default(false);
            $table->date('hipaa_training_date')->nullable();
            $table->date('hipaa_training_expiry')->nullable();
            
            // Contact & emergency
            $table->string('work_phone_encrypted', 512)->nullable();
            $table->string('work_email_encrypted', 512)->nullable();
            $table->json('emergency_contact_encrypted')->nullable();
            
            // System access
            $table->json('system_permissions')->nullable();
            $table->json('accessible_facility_ids')->nullable();
            $table->json('accessible_department_ids')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reports_to_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['employment_status', 'global_role_level']);
            $table->index(['license_expiry_date', 'employment_status']);
            $table->index(['dea_expiry_date', 'employment_status']);
        });

        /**DONE++
         * STAFF_CREDENTIALS - Time-stamped credential snapshots
         * Shard Strategy: Sharded by staff_id
         * Purpose: Immutable audit trail of credentialing events
         */
        Schema::create('staff_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('credential_uuid')->unique()->index();
            $table->unsignedBigInteger('staff_id')->index();
            
            // Credential details
            $table->enum('credential_type', [
                'medical_license',
                'board_certification',
                'dea_registration',
                'cds_registration',
                'malpractice_insurance',
                'professional_liability',
                'cpr_certification',
                'acls_certification',
                'pals_certification',
                'bls_certification',
                'specialty_training',
                'continuing_education',
                'privileging',
                'hospital_affiliation'
            ])->index();
            
            $table->string('credential_name', 200);
            $table->string('credential_number_encrypted', 512)->nullable();
            $table->string('credential_number_hash', 128)->nullable();
            
            // Issuing authority
            $table->string('issuing_authority', 200);
            $table->string('issuing_authority_contact', 200)->nullable();
            $table->string('issuing_state_country', 100)->nullable();
            
            // Validity period
            $table->date('issued_date')->index();
            $table->date('valid_from')->index();
            $table->date('valid_to')->nullable()->index();
            $table->boolean('requires_renewal')->default(true);
            $table->date('renewal_reminder_date')->nullable();
            
            // Verification
            $table->enum('verification_status', [
                'pending',
                'verified',
                'expired',
                'suspended',
                'revoked',
                'under_review'
            ])->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            $table->string('verification_method', 100)->nullable()->comment('primary_source, database_check, document_review');
            $table->text('verification_notes')->nullable();
            
            // Document management
            $table->string('credential_document_hash', 128)->comment('SHA-256 of uploaded document');
            $table->string('document_storage_path', 512)->nullable();
            $table->string('document_mime_type', 100)->nullable();
            $table->unsignedInteger('document_size_bytes')->nullable();
            
            // Compliance tracking
            $table->timestamp('snapshot_taken_at')->index()->comment('For audit trail reconstruction');
            $table->boolean('is_current')->default(true)->index();
            $table->unsignedBigInteger('superseded_by_credential_id')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            
            // Performance indexes
            $table->index(['staff_id', 'credential_type', 'is_current']);
            $table->index(['valid_to', 'verification_status']); // Expiry monitoring
            $table->index(['verification_status', 'valid_from']);
        });

        /**
         * ========================================================================
         * FACILITY & ORGANIZATIONAL STRUCTURE
         * ========================================================================
         */
        
        /**DONE++
         * FACILITIES - Healthcare facility registry
         * Shard Strategy: Reference data (CDN-distributed, cache-first)
         * DONE:
         */
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('facility_uuid')->unique()->index();
            
            // Facility identification
            $table->string('facility_code', 50)->unique()->index();
            $table->string('facility_name', 200);
            $table->string('legal_entity_name', 200);
            $table->string('tax_id_encrypted', 512)->nullable();
            
            // Facility classification
            $table->enum('facility_type', [
                'hospital',
                'clinic',
                'urgent_care',
                'emergency_department',
                'ambulatory_surgery_center',
                'diagnostic_center',
                'rehabilitation_center',
                'long_term_care',
                'hospice',
                'community_health_center',
                'specialty_center',
                'telehealth_hub'
            ])->index();
            
            $table->enum('facility_tier', ['tertiary', 'secondary', 'primary', 'specialized'])->index();
            $table->unsignedSmallInteger('bed_capacity')->nullable();
            $table->json('accreditations')->nullable()->comment('JCI, CIHQ, ISO certifications');
            
            // Location information
            $table->string('address_line1', 200);
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->index();
            $table->string('state_province', 100)->index();
            $table->string('postal_code', 20)->index();
            $table->string('country_code', 3)->index();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('timezone', 50)->default('UTC');
            
            // Contact information
            $table->string('main_phone', 50);
            $table->string('emergency_phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('website', 255)->nullable();
            
            // Operational hours
            $table->json('operating_hours')->comment('Weekly schedule with timezone');
            $table->json('emergency_services_hours')->nullable();
            $table->boolean('is_24_7')->default(false);
            
            // Network & affiliations
            $table->unsignedBigInteger('parent_organization_id')->nullable();
            $table->json('affiliated_facility_ids')->nullable();
            $table->json('referral_network_facility_ids')->nullable();
            $table->string('health_system_name', 200)->nullable();
            
            // Regulatory & compliance
            $table->string('license_number', 100)->nullable();
            $table->string('license_issuing_authority', 200)->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->json('regulatory_identifiers')->nullable()->comment('NPI, CMS ID, state IDs');
            $table->boolean('participates_in_medicare')->default(false);
            $table->boolean('participates_in_medicaid')->default(false);
            
            // Capabilities & services
            $table->json('available_services')->comment('Emergency, surgery, imaging, etc.');
            $table->json('specialty_services')->nullable();
            $table->json('equipment_inventory_summary')->nullable();
            $table->boolean('has_emergency_department')->default(false);
            $table->boolean('has_trauma_center')->default(false);
            $table->unsignedTinyInteger('trauma_center_level')->nullable()->comment('1-5, null if none');
            $table->boolean('has_intensive_care')->default(false);
            $table->boolean('has_neonatal_icu')->default(false);
            $table->boolean('has_cardiac_cath_lab')->default(false);
            
            // Data residency & sharding
            $table->string('data_residency_region', 10)->index();
            $table->string('primary_database_shard', 50)->index();
            $table->json('replica_shard_locations')->nullable();
            
            // Performance metrics
            $table->decimal('average_wait_time_minutes', 5, 2)->nullable();
            $table->decimal('patient_satisfaction_score', 3, 2)->nullable();
            $table->unsignedInteger('monthly_patient_volume')->nullable();
            
            // Status
            $table->enum('operational_status', [
                'fully_operational',
                'limited_services',
                'emergency_only',
                'temporarily_closed',
                'permanently_closed',
                'under_construction'
            ])->default('fully_operational')->index();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Performance indexes
            $table->index(['country_code', 'state_province', 'city']);
            $table->index(['facility_type', 'operational_status']);
            $table->index(['data_residency_region', 'primary_database_shard']);
        });

        /**
         * DEPARTMENTS - Facility organizational units
         * DONE++
         */
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('department_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            
            // Department identification
            $table->string('department_code', 50)->index();
            $table->string('department_name', 200);
            $table->enum('department_type', [
                'emergency',
                'intensive_care',
                'surgery',
                'outpatient',
                'inpatient',
                'radiology',
                'laboratory',
                'pharmacy',
                'physical_therapy',
                'cardiology',
                'oncology',
                'pediatrics',
                'obstetrics',
                'psychiatry',
                'administration',
                'support_services'
            ])->index();
            
            // Hierarchy
            $table->unsignedBigInteger('parent_department_id')->nullable();
            $table->unsignedBigInteger('department_head_staff_id')->nullable();
            
            // Capacity & resources
            $table->unsignedSmallInteger('bed_count')->nullable();
            $table->unsignedSmallInteger('treatment_room_count')->nullable();
            $table->unsignedSmallInteger('max_concurrent_capacity')->default(10);
            
            // Location
            $table->string('building', 100)->nullable();
            $table->string('floor', 20)->nullable();
            $table->string('wing_section', 50)->nullable();
            
            // Operational
            $table->json('operating_hours')->nullable();
            $table->boolean('accepts_walk_ins')->default(false);
            $table->boolean('requires_appointment')->default(true);
            $table->unsignedSmallInteger('average_wait_time_minutes')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'temporarily_closed'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('parent_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('department_head_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Unique constraint
            $table->unique(['facility_id', 'department_code']);
            
            // Performance indexes
            $table->index(['facility_id', 'department_type', 'status']);
        });

        /**DONE:++
         * FACILITY_STAFF_ROLES - Staff assignments to facilities
         * Shard Strategy: Sharded by facility_id
         */
        Schema::create('facility_staff_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('assignment_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            
            // Role definition
            $table->enum('role_code', [
                'attending_physician',
                'resident_physician',
                'consulting_physician',
                'surgeon',
                'anesthesiologist',
                'nurse_practitioner',
                'physician_assistant',
                'registered_nurse',
                'charge_nurse',
                'nurse_manager',
                'pharmacist',
                'pharmacy_technician',
                'radiologist',
                'radiologic_technician',
                'laboratory_scientist',
                'respiratory_therapist',
                'physical_therapist',
                'occupational_therapist',
                'social_worker',
                'case_manager',
                'receptionist',
                'medical_assistant',
                'facility_administrator',
                'department_manager',
                'quality_coordinator',
                'infection_control',
                'it_support'
            ])->index();
            
            $table->json('department_ids')->comment('Departments within facility where staff works');
            $table->boolean('is_primary_facility')->default(false);
            
            // Privileges at this facility
            $table->json('privileges_bitmask')->comment('Bitwise flags for specific privileges');
            $table->json('accessible_patient_populations')->nullable()->comment('Age groups, conditions, etc.');
            $table->json('prescribing_authority_at_facility')->nullable();
            
            // Schedule
            $table->json('shift_schedule')->nullable()->comment('Weekly schedule for this facility');
            $table->enum('shift_type', ['day', 'night', 'rotating', 'on_call', 'flexible'])->nullable();
            $table->unsignedSmallInteger('hours_per_week')->nullable();
            
            // Effective period
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->enum('assignment_status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active')->index();
            
            // Credentialing at facility
            $table->timestamp('credentialing_completed_at')->nullable();
            $table->unsignedBigInteger('credentialed_by_staff_id')->nullable();
            $table->timestamp('privileging_approved_at')->nullable();
            $table->timestamp('next_reappointment_date')->nullable();
            
            // Performance
            $table->unsignedInteger('patients_treated_at_facility')->default(0);
            $table->decimal('facility_satisfaction_score', 3, 2)->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            
            // Prevent duplicate active assignments
            $table->unique(['facility_id', 'staff_id', 'role_code', 'effective_from']);
            
            // Performance indexes
            $table->index(['facility_id', 'assignment_status', 'effective_from']);
            $table->index(['staff_id', 'is_primary_facility']);
            $table->index(['effective_to', 'assignment_status']); // For cleanup
        });

/**
 * DONE++
 */
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('invitation_uuid')->unique()->index();
            
            // Staff and assignment references
            $table->unsignedBigInteger('staff_id')->index();          // staff being invited
            $table->unsignedBigInteger('facility_id')->index();       // target facility
            $table->unsignedBigInteger('department_id')->nullable()->index(); // optional department
            $table->unsignedBigInteger('role_id')->nullable()->index();       // role assigned for this invitation
            
            // Invitation status
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending')->index();
            
            // Timing
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            // Audit / metadata
            $table->unsignedBigInteger('invited_by_staff_id')->nullable()->index(); // who sent the invitation
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            $table->foreign('invited_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['facility_id', 'department_id', 'status']);
            $table->index(['staff_id', 'status']);
        });


        /**
         * ========================================================================
         * CLUSTER 2: VISIT DOMAIN (Write-Optimized, Facility-Sharded)
         * ========================================================================
         * Shard Strategy: (facility_id, DATE(created_at))
         * Purpose: Core operational transaction processing
         * Consistency: Strong within shard, eventual across shards
         */
        
        /**DONE++
         * VISITS - Aggregate root for patient encounters
         * This is the core transactional table for all patient care episodes
         */
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('visit_uuid')->unique()->index()->comment('Public-facing identifier');
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Visit classification
            $table->enum('visit_type', [
                'outpatient',
                'inpatient',
                'emergency',
                'urgent_care',
                'virtual_telehealth',
                'home_health',
                'observation',
                'day_surgery',
                'consultation',
                'followup',
                'preventive_wellness'
            ])->index();
            
            $table->enum('visit_subtype', [
                'new_patient',
                'established_patient',
                'annual_physical',
                'sick_visit',
                'injury',
                'procedure',
                'diagnostic',
                'therapy_session'
            ])->nullable();
            
            // Triage & urgency
            $table->unsignedTinyInteger('acuity_score')->default(3)->index()->comment('1=Resuscitation, 2=Emergent, 3=Urgent, 4=Less Urgent, 5=Non-urgent');
            $table->json('chief_complaints')->comment('Primary reasons for visit');
            $table->json('symptoms_on_arrival')->nullable();
            $table->text('patient_reported_history')->nullable();
            
            // Arrival information
            $table->timestamp('arrived_at')->index();
            $table->timestamp('registered_at')->nullable();
            $table->enum('mode_of_arrival', [
                'walk_in',
                'ambulance',
                'private_vehicle',
                'police_transport',
                'air_ambulance',
                'wheelchair_transport',
                'transfer_from_facility'
            ])->nullable();
            $table->string('accompanying_person', 200)->nullable();
            
            // Referral tracking
            $table->unsignedBigInteger('referring_facility_id')->nullable();
            $table->unsignedBigInteger('referring_provider_staff_id')->nullable();
            $table->string('external_referral_id', 100)->nullable();
            $table->text('referral_reason')->nullable();
            
            // Current state (denormalized for performance)
            $table->unsignedBigInteger('current_department_id')->nullable()->index();
            $table->enum('current_phase', [
                'registration',
                'waiting_triage',
                'triage',
                'waiting_provider',
                'consultation',
                'diagnostic_tests',
                'awaiting_results',
                'treatment',
                'procedures',
                'observation',
                'admission_pending',
                'billing',
                'discharge_pending',
                'discharged',
                'left_without_being_seen',
                'left_against_medical_advice',
                'transferred',
                'admitted',
                'expired'
            ])->default('registration')->index();
            
            $table->timestamp('waiting_since')->nullable()->index()->comment('For queue management');
            $table->timestamp('clinical_care_started_at')->nullable();
            $table->timestamp('clinical_care_ended_at')->nullable();
            
            // Expected vs actual duration
            $table->unsignedSmallInteger('expected_duration_minutes')->nullable();
            $table->unsignedSmallInteger('actual_duration_minutes')->nullable();
            
            // Appointment linkage
            $table->unsignedBigInteger('scheduled_appointment_id')->nullable();
            $table->boolean('is_walk_in')->default(false)->index();
            $table->timestamp('scheduled_time')->nullable();
            
            // Insurance & authorization
            $table->string('insurance_preauth_id', 100)->nullable();
            $table->enum('insurance_verification_status', [
                'not_verified',
                'verified',
                'pending',
                'denied',
                'not_applicable'
            ])->default('not_verified');
            $table->timestamp('insurance_verified_at')->nullable();
            
            // Clinical summary (denormalized)
            $table->json('vital_signs_summary')->nullable()->comment('Latest vitals snapshot');
            $table->json('diagnosis_codes')->nullable()->comment('ICD-10 codes');
            $table->json('procedure_codes')->nullable()->comment('CPT codes');
            $table->json('medications_administered')->nullable();
            
            // Discharge information
            $table->timestamp('discharged_at')->nullable()->index();
            $table->unsignedBigInteger('discharged_by_staff_id')->nullable();
            $table->enum('discharge_disposition', [
                'home',
                'admitted_to_hospital',
                'transferred_to_facility',
                'left_ama',
                'left_without_seen',
                'expired',
                'hospice',
                'skilled_nursing_facility',
                'rehabilitation_facility',
                'psychiatric_facility',
                'law_enforcement_custody'
            ])->nullable();
            $table->text('discharge_instructions')->nullable();
            $table->json('discharge_medications')->nullable();
            $table->timestamp('followup_scheduled_at')->nullable();
            $table->unsignedBigInteger('followup_provider_staff_id')->nullable();
            
            // Quality & safety flags
            $table->boolean('sentinel_event_flagged')->default(false)->index();
            $table->json('safety_alerts')->nullable()->comment('Fall risk, allergy alerts, etc.');
            $table->boolean('requires_interpreter')->default(false);
            $table->string('interpreter_language', 50)->nullable();
            $table->boolean('isolation_required')->default(false);
            $table->string('isolation_type', 50)->nullable();
            
            // Financial snapshot
            $table->decimal('estimated_total_charges', 12, 2)->nullable();
            $table->decimal('patient_estimated_responsibility', 10, 2)->nullable();
            $table->enum('payment_status', [
                'not_billed',
                'pending',
                'partially_paid',
                'paid_in_full',
                'insurance_pending',
                'denied',
                'bad_debt',
                'charity_care'
            ])->default('not_billed');
            
            // Status & workflow
            $table->enum('status', [
                'active',
                'completed',
                'cancelled',
                'no_show',
                'in_progress'
            ])->default('active')->index();
            
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('current_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('referring_facility_id')->references('id')->on('facilities')->onDelete('set null');
            
            // Critical performance indexes (optimized for operational queries)
            $table->index(['facility_id', 'status', 'current_phase']);
            $table->index(['facility_id', 'arrived_at', 'status']); // Shard key pattern
            $table->index(['patient_id', 'arrived_at']); // Patient history
            $table->index(['current_department_id', 'waiting_since', 'status']); // Queue management
            $table->index(['acuity_score', 'waiting_since', 'status']); // Priority sorting
            $table->index(['discharged_at', 'status']); // Cleanup queries
        });

        /**DONE++
         * VISIT_EVENTS - Immutable event log (Event Sourcing pattern)
         * Purpose: Complete audit trail of all visit state changes
         */
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

        /**DONE++
         * VISIT_ACTORS - Staff participation in visits
         * Purpose: Track who did what during the visit (for billing & compliance)
         */
        Schema::create('visit_actors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            
            // Role snapshot (frozen at time of participation)
            $table->string('role_at_time', 100)->comment('Snapshot from facility_staff_roles');
            $table->unsignedBigInteger('credential_snapshot_id')->nullable();
            
            // Participation type
            $table->enum('participation_type', [
                'primary_provider',
                'consulting_provider',
                'assisting_provider',
                'supervising_provider',
                'nurse_primary',
                'nurse_assisting',
                'triage_nurse',
                'anesthesiologist',
                'surgical_assistant',
                'pharmacist',
                'technician',
                'therapist',
                'documenting_staff',
                'administrative',
                'observer_trainee'
            ])->index();
            
            // Time involvement
            $table->timestamp('participation_started_at')->index();
            $table->timestamp('participation_ended_at')->nullable()->index();
            $table->unsignedSmallInteger('time_involvement_minutes')->nullable();
            
            // Context
            $table->unsignedBigInteger('department_id_at_time')->nullable();
            $table->json('services_performed')->nullable()->comment('CPT codes performed by this staff');
            $table->json('procedures_assisted')->nullable();
            
            // Billing relevance
            $table->boolean('is_billable_provider')->default(false);
            $table->decimal('provider_charge_amount', 10, 2)->nullable();
            
            // Quality & teaching
            $table->boolean('is_teaching_case')->default(false);
            $table->unsignedBigInteger('supervising_staff_id')->nullable();
            
            // Audit
            $table->timestamps();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('credential_snapshot_id')->references('id')->on('staff_credentials')->onDelete('set null');
            
            // Prevent duplicate participation records
            $table->unique(['visit_id', 'staff_id', 'participation_type', 'participation_started_at']);
            
            // Performance indexes
            $table->index(['facility_id', 'staff_id', 'participation_started_at']);
            $table->index(['staff_id', 'participation_started_at']);
            $table->index(['visit_id', 'participation_type']);
        });

        /**DONE++
         * VISIT_ROUTES - Department routing history
         * Purpose: Track patient flow through facility departments
         */
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
            
            // Performance indexes
            $table->index(['facility_id', 'routed_at']);
            $table->index(['visit_id', 'routed_at']);
            $table->index(['to_department_id', 'routed_at']);
            $table->index(['routing_reason', 'routed_at']);
        });

        /**
         * ========================================================================
         * CLINICAL DOMAIN - Medical Records & AI Integration
         * ========================================================================
         */
        
        /**DONE++
         * CLINICAL_ENCOUNTERS - Core medical documentation
         * Shard Strategy: Co-located with visit (facility_id, visit_id)
         */
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
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['facility_id', 'visit_id']);
            $table->index(['patient_id', 'documented_at']);
            $table->index(['primary_provider_staff_id', 'documented_at']);
            $table->index(['documentation_status', 'documented_at']);
        });

        /**DONE++
         * AI_ASSESSMENTS - AI/ML clinical decision support records
         * Regulatory Compliance: FDA 510(k), EU MDR for AI/ML medical devices
         */
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
            
            // Foreign keys
            $table->foreign('clinical_encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('reviewed_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['facility_id', 'assessed_at']);
            $table->index(['ai_model_name', 'ai_model_version', 'assessed_at']);
            $table->index(['human_review_status', 'assessed_at']);
            $table->index(['patient_id', 'assessed_at']);
        });

        /**
         * ========================================================================
         * SERVICE CATALOG & BILLING DOMAIN
         * ========================================================================
         */
        
        /**DONE++
         * SERVICE_CATALOGS - Master service/procedure definitions
         * Shard Strategy: Reference data (cache-first, CDN-distributed)
         */
        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->uuid('service_uuid')->unique()->index();
            
            // Service identification
            $table->string('service_code', 50)->unique()->index()->comment('CPT, ICD, HCPCS, or local code');
            $table->enum('code_system', [
                'cpt',              // Current Procedural Terminology
                'hcpcs',           // Healthcare Common Procedure Coding System
                'icd_10_pcs',      // ICD-10 Procedure Coding System
                'cdt',             // Dental codes
                'local_custom'     // Facility-specific codes
            ])->index();
            
            $table->string('service_name', 300);
            $table->text('service_description')->nullable();
            $table->json('alternate_names')->nullable();
            
            // Classification
            $table->enum('service_category', [
                'evaluation_management',
                'diagnostic_imaging',
                'laboratory_test',
                'surgical_procedure',
                'medical_procedure',
                'therapy_session',
                'preventive_care',
                'vaccination',
                'medication_administration',
                'emergency_service',
                'consultation',
                'anesthesia',
                'pathology',
                'radiology',
                'facility_fee'
            ])->index();
            
            $table->json('service_subcategories')->nullable();
            $table->string('department_specialty', 100)->nullable();
            
            // Regulatory & compliance
            $table->json('regulatory_approval_status')->comment('FDA, state licensing, etc.');
            $table->json('required_certifications')->nullable();
            $table->json('minimum_required_credentials')->comment('Staff qualifications needed');
            $table->json('required_equipment')->nullable();
            $table->json('required_facility_capabilities')->nullable();
            
            // Clinical information
            $table->unsignedSmallInteger('default_duration_minutes')->nullable();
            $table->json('typical_indications')->nullable()->comment('When this service is appropriate');
            $table->json('contraindications')->nullable();
            $table->json('prerequisites')->nullable()->comment('Services that must precede this');
            $table->json('commonly_paired_services')->nullable();
            
            // Risk & consent
            $table->enum('risk_level', ['low', 'moderate', 'high', 'critical'])->default('low');
            $table->boolean('requires_informed_consent')->default(false);
            $table->string('consent_form_template', 200)->nullable();
            
            // Geographic & regulatory coverage
            $table->string('applicable_region', 10)->index()->comment('US, EU, APAC, etc.');
            $table->json('approved_countries')->nullable();
            $table->json('state_specific_regulations')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'deprecated', 'under_review'])->default('active')->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Performance indexes
            $table->index(['service_category', 'status']);
            $table->index(['code_system', 'service_code']);
        });

        /**DONE++
         * SERVICE_VERSIONS - Versioned pricing & terms
         * Purpose: Historical pricing accuracy for billing disputes
         */
        Schema::create('service_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('version_uuid')->unique()->index();
            $table->unsignedBigInteger('service_catalog_id')->index();
            $table->unsignedBigInteger('facility_id')->nullable()->index()->comment('Facility-specific pricing');
            
            // Version control
            $table->string('version_number', 20)->index();
            $table->date('valid_from')->index();
            $table->date('valid_to')->nullable()->index();
            $table->boolean('is_current')->default(true)->index();
            
            // Pricing information
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('base_price_amount', 12, 2);
            $table->decimal('facility_markup_percentage', 5, 2)->nullable();
            $table->decimal('final_price_amount', 12, 2);
            
            // Insurance & coverage
            $table->json('insurance_coverage_rates')->nullable()->comment('Coverage % by insurance type');
            $table->boolean('requires_preauthorization')->default(false);
            $table->json('preauthorization_criteria')->nullable();
            $table->unsignedSmallInteger('preauth_processing_days')->nullable();
            
            // Billing rules
            $table->boolean('is_billable')->default(true);
            $table->enum('billing_method', [
                'per_service',
                'per_unit',
                'per_hour',
                'per_day',
                'flat_fee',
                'bundled',
                'not_separately_billable'
            ])->default('per_service');
            
            $table->decimal('minimum_billable_units', 8, 2)->default(1);
            $table->decimal('maximum_billable_units', 8, 2)->nullable();
            $table->json('bundled_service_ids')->nullable();
            
            // Modifiers
            $table->json('allowed_modifiers')->nullable()->comment('CPT modifiers');
            $table->json('modifier_price_adjustments')->nullable();
            
            // Documentation requirements
            $table->text('documentation_requirements')->nullable();
            $table->text('medical_necessity_criteria')->nullable();
            $table->json('required_diagnosis_codes')->nullable()->comment('ICD-10 codes for coverage');
            
            // Cost accounting
            $table->decimal('direct_cost', 10, 2)->nullable();
            $table->decimal('indirect_cost', 10, 2)->nullable();
            $table->decimal('target_margin_percentage', 5, 2)->nullable();
            
            // Audit & snapshot
            $table->json('version_snapshot')->comment('Full service details at this point in time');
            $table->string('version_hash', 128)->comment('SHA-256 for integrity');
            $table->text('change_notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('service_catalog_id')->references('id')->on('service_catalogs')->onDelete('cascade');
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['service_catalog_id', 'facility_id', 'version_number']);
            
            // Performance indexes
            $table->index(['service_catalog_id', 'valid_from', 'valid_to']);
            $table->index(['facility_id', 'is_current']);
        });

        /**DONE++
         * BILLING_CYCLES - Financial period aggregation
         * Shard Strategy: Sharded by (facility_id, visit_id)
         */
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->uuid('billing_cycle_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Cycle definition
            $table->enum('cycle_type', [
                'visit_based',          // Single visit billing
                'admission_discharge',  // Inpatient stay
                'daily_inpatient',     // Daily charges
                'weekly_inpatient',    // Weekly billing
                'procedure_based',     // Single procedure
                'bundled_payment',     // DRG or bundled
                'subscription'         // Ongoing care plan
            ])->index();
            
            // Time period
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->nullable()->index();
            $table->unsignedSmallInteger('days_in_cycle')->nullable();
            
            // Financial summary
            $table->decimal('total_amount_charged', 12, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            
            // Insurance processing
            $table->string('primary_insurance_claim_number', 100)->nullable();
            $table->decimal('insurance_covered_amount', 10, 2)->default(0);
            $table->decimal('insurance_adjustment_amount', 10, 2)->default(0);
            $table->decimal('insurance_payment_received', 10, 2)->default(0);
            $table->timestamp('insurance_claim_submitted_at')->nullable();
            $table->timestamp('insurance_payment_received_at')->nullable();
            
            // Patient responsibility
            $table->decimal('patient_responsibility_amount', 10, 2)->default(0);
            $table->decimal('patient_copay_amount', 10, 2)->default(0);
            $table->decimal('patient_deductible_amount', 10, 2)->default(0);
            $table->decimal('patient_coinsurance_amount', 10, 2)->default(0);
            $table->decimal('patient_payment_received', 10, 2)->default(0);
            
            // Discounts & adjustments
            $table->decimal('discount_applied', 10, 2)->default(0);
            $table->string('discount_reason', 200)->nullable();
            $table->decimal('contractual_adjustment', 10, 2)->default(0);
            $table->decimal('charity_care_adjustment', 10, 2)->default(0);
            $table->decimal('bad_debt_adjustment', 10, 2)->default(0);
            
            // Tax & fees
            $table->json('tax_details')->nullable();
            $table->decimal('total_tax_amount', 10, 2)->default(0);
            
            // Billing status
            $table->enum('billing_status', [
                'draft',
                'pending_review',
                'pending_submission',
                'submitted_to_insurance',
                'partially_paid',
                'paid_in_full',
                'payment_plan',
                'collections',
                'disputed',
                'written_off',
                'charity_care'
            ])->default('draft')->index();
            
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('payment_due_date')->nullable();
            $table->unsignedSmallInteger('days_outstanding')->nullable();
            
            // Collections & follow-up
            $table->unsignedTinyInteger('statement_count')->default(0);
            $table->timestamp('last_statement_sent_at')->nullable();
            $table->timestamp('sent_to_collections_at')->nullable();
            $table->string('collections_agency', 200)->nullable();
            
            // Dispute management
            $table->boolean('is_disputed')->default(false);
            $table->text('dispute_reason')->nullable();
            $table->timestamp('dispute_opened_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['facility_id', 'billing_status', 'period_start']);
            $table->index(['patient_id', 'billing_status']);
            $table->index(['billing_status', 'payment_due_date']);
        });

        /**DONE++
         * INVOICE_LINE_ITEMS - Detailed billing transactions
         * Purpose: Immutable snapshot of services rendered
         */
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('line_item_uuid')->unique()->index();
            $table->unsignedBigInteger('billing_cycle_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            
            // Service identification (frozen snapshot)
            $table->unsignedBigInteger('service_version_id')->index();
            $table->json('service_version_snapshot')->comment('Frozen pricing/terms at time of service');
            $table->string('service_code', 50)->index();
            $table->string('service_description', 500);
            
            // Quantity & pricing
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit_of_measure', 50)->default('each');
            $table->decimal('unit_price_at_time', 10, 2);
            $table->decimal('line_total_amount', 12, 2);
            
            // Discounts & adjustments
            $table->decimal('applied_discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('adjustment_amount', 10, 2)->default(0);
            $table->text('adjustment_reason')->nullable();
            $table->decimal('net_amount', 12, 2);
            
            // Service delivery context
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('staff_performed_id')->nullable()->index();
            $table->timestamp('service_performed_at')->index();
            $table->unsignedSmallInteger('service_duration_minutes')->nullable();
            
            // Clinical justification
            $table->json('diagnosis_codes')->nullable()->comment('ICD-10 codes justifying service');
            $table->text('medical_necessity_notes')->nullable();
            $table->json('modifier_codes')->nullable()->comment('CPT modifiers applied');
            
            // Insurance & billing codes
            $table->string('revenue_code', 20)->nullable()->comment('UB-04 revenue code');
            $table->string('procedure_code', 20)->nullable()->comment('CPT/HCPCS code');
            $table->json('insurance_specific_codes')->nullable();
            
            // Approval & authorization
            $table->string('preauthorization_number', 100)->nullable();
            $table->boolean('requires_review')->default(false);
            $table->boolean('coding_reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by_staff_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            // Status
            $table->enum('line_item_status', [
                'pending',
                'approved',
                'billed',
                'paid',
                'denied',
                'adjusted',
                'written_off'
            ])->default('pending')->index();
            
            // Audit & integrity
            $table->string('audit_trail_hash', 128)->comment('SHA-256 for tamper detection');
            $table->timestamps();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('billing_cycle_id')->references('id')->on('billing_cycles')->onDelete('cascade');
            $table->foreign('service_version_id')->references('id')->on('service_versions')->onDelete('restrict');
            $table->foreign('staff_performed_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['billing_cycle_id', 'service_performed_at']);
            $table->index(['service_code', 'service_performed_at']);
            $table->index(['staff_performed_id', 'service_performed_at']);
        });

        /**
         * ========================================================================
         * INVENTORY & PHARMACY DOMAIN
         * ========================================================================
         */
        
        /**DONE++
         * INVENTORY_ITEMS - Master inventory catalog
         */
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('item_uuid')->unique()->index();
            
            // Item identification
            $table->string('item_code', 100)->unique()->index();
            $table->string('item_name', 300);
            $table->text('item_description')->nullable();
            
            // Classification
            $table->enum('item_category', [
                'medication',
                'medical_supply',
                'surgical_instrument',
                'diagnostic_equipment',
                'implantable_device',
                'prosthetic',
                'laboratory_reagent',
                'personal_protective_equipment',
                'administrative_supply',
                'other'
            ])->index();
            
            $table->string('item_subcategory', 100)->nullable();
            
            // Medication-specific fields
            $table->string('generic_name', 300)->nullable();
            $table->string('brand_name', 300)->nullable();
            $table->string('ndc_code', 20)->nullable()->index()->comment('National Drug Code');
            $table->string('drug_class', 100)->nullable();
            $table->enum('controlled_substance_schedule', ['I', 'II', 'III', 'IV', 'V', 'non_controlled'])->nullable();
            $table->json('active_ingredients')->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('route_of_administration', 100)->nullable();
            
            // Manufacturer information
            $table->string('manufacturer', 200)->nullable();
            $table->string('manufacturer_item_number', 100)->nullable();
            $table->string('supplier', 200)->nullable();
            
            // Unit information
            $table->string('unit_of_measure', 50)->default('each');
            $table->unsignedSmallInteger('package_quantity')->default(1);
            $table->string('packaging_type', 100)->nullable();
            
            // Pricing
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('average_wholesale_price', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('USD');
            
            // Storage & handling
            $table->json('storage_requirements')->nullable()->comment('Temperature, humidity, light');
            $table->boolean('requires_refrigeration')->default(false);
            $table->boolean('requires_controlled_access')->default(false);
            $table->string('storage_location_type', 100)->nullable();
            
            // Regulatory
            $table->boolean('requires_prescription')->default(false);
            $table->json('regulatory_approvals')->nullable();
            $table->string('fda_approval_number', 100)->nullable();
            
            // Safety information
            $table->boolean('is_hazardous')->default(false);
            $table->json('safety_warnings')->nullable();
            $table->json('contraindications')->nullable();
            $table->text('special_handling_instructions')->nullable();
            
            // Inventory management
            $table->boolean('is_billable')->default(true);
            $table->boolean('track_by_lot')->default(false);
            $table->boolean('track_by_serial')->default(false);
            $table->unsignedSmallInteger('reorder_point')->nullable();
            $table->unsignedSmallInteger('reorder_quantity')->nullable();
            $table->unsignedSmallInteger('safety_stock_level')->nullable();
            $table->unsignedSmallInteger('max_stock_level')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'discontinued', 'recalled'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Performance indexes
            $table->index(['item_category', 'status']);
            $table->index(['controlled_substance_schedule', 'status']);
        });

        /**DONE++
         * INVENTORY_LEDGER - Double-entry inventory accounting
         * Purpose: Immutable transaction log (like accounting ledger)
         */
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->index();
            
            // Transaction details
            $table->enum('transaction_type', [
                'purchase',
                'adjustment_increase',
                'adjustment_decrease',
                'consumption_visit',
                'consumption_waste',
                'return_to_supplier',
                'transfer_in',
                'transfer_out',
                'cycle_count',
                'expired',
                'damaged',
                'stolen',
                'recalled'
            ])->index();
            
            // Quantity tracking (double-entry: always shows net change)
            $table->decimal('quantity_change', 10, 2)->comment('Positive=in, Negative=out');
            $table->decimal('balance_after_transaction', 10, 2)->index();
            $table->string('unit_of_measure', 50);
            
            // Lot & serial tracking
            $table->string('lot_number', 100)->nullable()->index();
            $table->string('serial_number', 100)->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->date('manufacture_date')->nullable();
            
            // Financial tracking
            $table->decimal('unit_cost_at_transaction', 10, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            
            // Context & linkage
            $table->unsignedBigInteger('reference_visit_id')->nullable()->index();
            $table->unsignedBigInteger('reference_prescription_id')->nullable()->index();
            $table->unsignedBigInteger('reference_purchase_order_id')->nullable();
            $table->unsignedBigInteger('transfer_from_facility_id')->nullable();
            $table->unsignedBigInteger('transfer_to_facility_id')->nullable();
            
            // Reason & documentation
            $table->enum('transaction_cause', [
                'manual_entry',
                'system_automated',
                'physical_count',
                'reconciliation',
                'patient_use',
                'procedural_use',
                'administrative'
            ])->index();
            
            $table->text('transaction_notes')->nullable();
            $table->string('reference_document_number', 100)->nullable();
            
            // Approval & verification
            $table->unsignedBigInteger('performed_by_staff_id')->nullable()->index();
            $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            // Storage location
            $table->string('storage_location', 200)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            
            // Audit & immutability
            $table->timestamp('transaction_timestamp')->index();
            $table->timestamp('created_at');
            $table->string('transaction_hash', 128)->comment('SHA-256 for integrity');
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('reference_visit_id')->references('id')->on('visits')->onDelete('set null');
            
            // Performance indexes (critical for inventory queries)
            $table->index(['facility_id', 'inventory_item_id', 'transaction_timestamp']);
            $table->index(['lot_number', 'expiry_date']);
            $table->index(['transaction_type', 'transaction_timestamp']);
        });

        /**DONE++
         * PRESCRIPTIONS - Medication orders
         */
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('prescription_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Prescriber information
            $table->unsignedBigInteger('prescribing_provider_staff_id')->index();
            $table->string('prescriber_npi', 20)->nullable();
            $table->string('prescriber_dea_number_encrypted', 512)->nullable();
            
            // Medication details
            $table->unsignedBigInteger('inventory_item_id')->index();
            $table->string('medication_name', 300);
            $table->string('generic_name', 300)->nullable();
            $table->string('ndc_code', 20)->nullable();
            $table->enum('controlled_substance_schedule', ['I', 'II', 'III', 'IV', 'V', 'non_controlled'])->nullable()->index();
            
            // Dosing instructions
            $table->string('dosage_strength', 100);
            $table->string('dosage_form', 100);
            $table->string('route', 100);
            $table->text('sig_instructions')->comment('Patient-facing directions');
            $table->text('pharmacist_notes')->nullable();
            
            // Quantity & refills
            $table->decimal('quantity_prescribed', 8, 2);
            $table->string('quantity_unit', 50);
            $table->unsignedTinyInteger('refills_allowed')->default(0);
            $table->unsignedTinyInteger('refills_remaining')->default(0);
            $table->unsignedSmallInteger('days_supply')->nullable();
            
            // Clinical context
            $table->json('diagnosis_codes')->nullable()->comment('ICD-10 justification');
            $table->text('clinical_indication')->nullable();
            $table->json('drug_allergy_check_results')->nullable();
            $table->json('drug_interaction_check_results')->nullable();
            
            // Validity period
            $table->timestamp('prescribed_at')->index();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->date('do_not_fill_before')->nullable();
            
            // Authorization
            $table->boolean('requires_prior_authorization')->default(false);
            $table->string('prior_authorization_number', 100)->nullable();
            $table->enum('prior_auth_status', ['not_required', 'pending', 'approved', 'denied'])->nullable();
            
            // Electronic prescribing
            $table->boolean('is_electronic_prescription')->default(true);
            $table->string('erx_message_id', 100)->nullable();
            $table->timestamp('transmitted_at')->nullable();
            $table->string('transmit_to_pharmacy', 300)->nullable();
            $table->string('pharmacy_ncpdp_id', 20)->nullable();
            
            // Dispense tracking
            $table->enum('dispense_status', [
                'pending',
                'transmitted',
                'received_by_pharmacy',
                'in_progress',
                'ready_for_pickup',
                'dispensed',
                'not_picked_up',
                'cancelled',
                'discontinued'
            ])->default('pending')->index();
            
            // Safety & monitoring
            $table->boolean('is_high_risk_medication')->default(false);
            $table->json('safety_monitoring_required')->nullable();
            $table->text('special_instructions')->nullable();
            
            // Status
            $table->enum('status', [
                'active',
                'completed',
                'cancelled',
                'discontinued',
                'expired',
                'on_hold'
            ])->default('active')->index();
            
            $table->text('status_reason')->nullable();
            $table->timestamp('discontinued_at')->nullable();
            $table->unsignedBigInteger('discontinued_by_staff_id')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('set null');
            $table->foreign('prescribing_provider_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['patient_id', 'status', 'prescribed_at']);
            $table->index(['prescribing_provider_staff_id', 'prescribed_at']);
            $table->index(['facility_id', 'dispense_status']);
            $table->index(['controlled_substance_schedule', 'prescribed_at']);
        });

        /**DOMN++
         * MEDICATION_DISPENSES - Pharmacy fulfillment records
         */
        Schema::create('medication_dispenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('dispense_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('prescription_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Dispense details (frozen snapshot)
            $table->json('prescription_details_snapshot')->comment('Frozen prescription at time of dispense');
            $table->unsignedBigInteger('dispensed_inventory_ledger_id')->nullable();
            
            // Quantity dispensed
            $table->decimal('quantity_dispensed', 8, 2);
            $table->string('quantity_unit', 50);
            $table->string('lot_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            
            // Staff verification (4-eyes principle)
            $table->unsignedBigInteger('dispensed_by_staff_id')->index();
            $table->timestamp('dispensed_at')->index();
            $table->unsignedBigInteger('checked_by_staff_id')->nullable()->comment('Pharmacist verification');
            $table->timestamp('checked_at')->nullable();
            $table->text('pharmacist_notes')->nullable();
            
            // Patient education & counseling
            $table->boolean('patient_counseling_provided')->default(false);
            $table->boolean('medication_guide_provided')->default(false);
            $table->text('patient_education_topics')->nullable();
            $table->text('patient_questions_addressed')->nullable();
            
            // Instructions
            $table->text('dispensed_instructions')->nullable();
            $table->text('followup_instructions')->nullable();
            $table->json('warning_labels_applied')->nullable();
            
            // Safety checks performed
            $table->json('safety_checks_performed')->comment('Allergy, interaction, duplicate therapy');
            $table->boolean('all_safety_checks_passed')->default(true);
            $table->json('safety_check_overrides')->nullable();
            $table->text('override_justification')->nullable();
            
            // Delivery method
            $table->enum('delivery_method', [
                'pickup_in_person',
                'mail_order',
                'delivery_service',
                'administered_in_facility',
                'sent_to_home_health'
            ])->nullable();
            
            $table->timestamp('picked_up_at')->nullable();
            $table->string('picked_up_by_name', 200)->nullable();
            $table->string('pickup_id_verified', 100)->nullable();
            
            // Billing
            $table->decimal('copay_collected', 8, 2)->nullable();
            $table->decimal('total_cost_to_patient', 10, 2)->nullable();
            $table->decimal('insurance_payment', 10, 2)->nullable();
            
            // Status
            $table->enum('status', ['dispensed', 'not_picked_up', 'returned', 'destroyed'])->default('dispensed')->index();
            
            // Audit
            $table->timestamps();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('dispensed_by_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('checked_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('dispensed_inventory_ledger_id')->references('id')->on('inventory_ledger')->onDelete('set null');
            
            // Performance indexes
            $table->index(['prescription_id', 'dispensed_at']);
            $table->index(['patient_id', 'dispensed_at']);
            $table->index(['facility_id', 'dispensed_at']);
        });

        /**
         * ========================================================================
         * CQRS READ MODELS (Materialized Views for Performance)
         * ========================================================================
         */
        
        /**DONE++
         * VISIT_CURRENT_STATES - Real-time visit status (materialized view)
         * Refresh Strategy: CDC (Change Data Capture) from visit_events
         */
        Schema::create('visit_current_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_id')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Current location & phase
            $table->unsignedBigInteger('current_department_id')->nullable()->index();
            $table->enum('current_phase', [
                'registration', 'waiting_triage', 'triage', 'waiting_provider',
                'consultation', 'diagnostic_tests', 'awaiting_results', 'treatment',
                'procedures', 'observation', 'billing', 'discharge_pending', 'discharged'
            ])->index();
            
            // Wait time tracking
            $table->timestamp('waiting_since')->nullable()->index();
            $table->unsignedSmallInteger('total_wait_minutes')->nullable();
            $table->unsignedSmallInteger('current_phase_duration_minutes')->nullable();
            
            // Next action
            $table->timestamp('next_scheduled_action_at')->nullable()->index();
            $table->string('next_action_type', 100)->nullable();
            $table->json('pending_tasks')->nullable();
            $table->unsignedTinyInteger('pending_tasks_count')->default(0);
            
            // Critical alerts
            $table->json('critical_alerts')->nullable();
            $table->boolean('has_critical_alerts')->default(false)->index();
            $table->unsignedTinyInteger('acuity_score')->index();
            
            // Staff assignment
            $table->json('staff_assigned_ids')->nullable();
            $table->unsignedBigInteger('primary_provider_staff_id')->nullable()->index();
            $table->unsignedBigInteger('primary_nurse_staff_id')->nullable();
            
            // Clinical snapshot
            $table->json('recent_vitals_last_reading')->nullable();
            $table->timestamp('vitals_last_recorded_at')->nullable();
            $table->json('active_orders')->nullable();
            $table->unsignedTinyInteger('active_orders_count')->default(0);
            
            // Estimated completion
            $table->timestamp('estimated_completion_time')->nullable();
            $table->unsignedSmallInteger('estimated_minutes_remaining')->nullable();
            
            // Update tracking
            $table->timestamp('last_event_at')->index();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->timestamp('materialized_at')->index();
            
            // Indexes for queue management
            $table->index(['current_department_id', 'waiting_since', 'acuity_score']);
            $table->index(['facility_id', 'current_phase', 'waiting_since']);
            $table->index(['has_critical_alerts', 'acuity_score']);
        });

        /**DONE++
         * DEPARTMENT_QUEUE_VIEWS - Real-time department operations dashboard
         * Refresh Strategy: 30-second batch update
         */
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
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            // Unique constraint (one row per department-queue type)
            $table->unique(['department_id', 'queue_type']);
            
            // Performance indexes
            $table->index(['facility_id', 'capacity_status', 'snapshot_at']);
            $table->index(['department_id', 'snapshot_at']);
        });

        /**DONE++
         * PATIENT_VISIT_SUMMARY_VIEWS - Patient portal & care coordination
         * Refresh Strategy: Nightly batch + real-time for active visits
         */
        Schema::create('patient_visit_summary_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id')->unique()->index();
            
            // Active visits
            $table->json('active_visit_ids')->nullable();
            $table->unsignedTinyInteger('active_visits_count')->default(0);
            
            // Recent history
            $table->json('recent_visits_last_30_days')->nullable();
            $table->unsignedTinyInteger('visits_last_30_days_count')->default(0);
            $table->timestamp('last_visit_date')->nullable()->index();
            $table->unsignedBigInteger('last_visit_facility_id')->nullable();
            
            // Upcoming appointments
            $table->json('upcoming_appointments')->nullable();
            $table->timestamp('next_appointment_at')->nullable()->index();
            
            // Prescriptions
            $table->json('active_prescriptions')->nullable();
            $table->json('pending_prescriptions')->nullable();
            $table->unsignedTinyInteger('active_prescriptions_count')->default(0);
            
            // Financial
            $table->decimal('outstanding_bills_total', 12, 2)->default(0);
            $table->unsignedTinyInteger('unpaid_invoices_count')->default(0);
            $table->json('payment_plans')->nullable();
            
            // Health metrics trends
            $table->json('health_metrics_trends')->nullable()->comment('Weight, BP, glucose trends');
            $table->json('recent_lab_results')->nullable();
            $table->json('recent_imaging_results')->nullable();
            
            // Care team
            $table->json('care_team_members')->nullable();
            $table->unsignedBigInteger('primary_care_provider_id')->nullable();
            
            // Preventive care
            $table->json('preventive_care_due')->nullable();
            $table->json('immunizations_due')->nullable();
            $table->json('screenings_due')->nullable();
            
            // Alerts & notifications
            $table->json('patient_alerts')->nullable();
            $table->unsignedTinyInteger('unread_messages_count')->default(0);
            
            // Update tracking
            $table->timestamp('last_updated_at')->index();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            
            // Performance indexes
            $table->index(['last_visit_date', 'active_visits_count']);
            $table->index(['next_appointment_at']);
        });

        /**
         * ========================================================================
         * AUDIT & GOVERNANCE DOMAIN
         * ========================================================================
         */
        
        /**DONE++
         * AUDIT_LOGS - Immutable compliance audit trail
         * Shard Strategy: (entity_type, DATE(created_at))
         * Retention: 7 years minimum (HIPAA requirement)
         */
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('audit_uuid')->unique()->index();
            
            // Operation details
            $table->enum('operation', [
                'create',
                'read',
                'update',
                'delete',
                'access',
                'export',
                'print',
                'share',
                'consent_change',
                'authentication',
                'authorization_failure'
            ])->index();
            
            // Entity information
            $table->string('entity_type', 100)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('entity_identifier', 200)->nullable();
            
            // Change tracking
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            
            // Actor information
            $table->enum('performed_by_type', ['staff', 'patient', 'system', 'external_api', 'scheduled_job'])->index();
            $table->unsignedBigInteger('performed_by_id')->nullable()->index();
            $table->string('performed_by_identifier', 200)->nullable();
            $table->string('performed_by_role', 100)->nullable();
            
            // Request context
            $table->string('request_id', 100)->index()->comment('Correlation ID for distributed tracing');
            $table->string('session_id', 100)->nullable();
            $table->string('user_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('geolocation', 100)->nullable();
            
            // Compliance & legal
            $table->enum('compliance_reason', [
                'treatment',
                'payment',
                'healthcare_operations',
                'billing',
                'audit',
                'research',
                'legal_request',
                'patient_request',
                'emergency_access',
                'break_glass'
            ])->index();
            
            $table->boolean('legal_hold_flag')->default(false)->index();
            $table->text('justification')->nullable();
            
            // Facility & department context
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable();
            
            // Patient privacy tracking (for HIPAA accounting of disclosures)
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->boolean('phi_accessed')->default(false)->index()->comment('Protected Health Information');
            $table->json('phi_fields_accessed')->nullable();
            
            // Outcome
            $table->enum('result', ['success', 'failure', 'partial', 'denied'])->index();
            $table->text('failure_reason')->nullable();
            $table->string('error_code', 50)->nullable();
            
            // Performance
            $table->unsignedSmallInteger('operation_duration_ms')->nullable();
            
            // Timestamp (immutable)
            $table->timestamp('created_at')->index();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Composite indexes for common compliance queries
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['performed_by_type', 'performed_by_id', 'created_at']);
            $table->index(['patient_id', 'phi_accessed', 'created_at']); // HIPAA accounting
            $table->index(['compliance_reason', 'created_at']);
            $table->index(['facility_id', 'created_at']);
            $table->index(['legal_hold_flag', 'created_at']);
        });

        /**DONE++
         * DATA_RESIDENCY_RULES - Regional compliance policies
         * Purpose: Enforce GDPR, HIPAA, and local data protection laws
         */
        Schema::create('data_residency_rules', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 10)->unique()->index();
            $table->string('region_name', 200);
            
            // Data categories subject to rules
            $table->enum('data_category', [
                'clinical_records',
                'financial_data',
                'identity_information',
                'audit_logs',
                'research_data',
                'genomic_data'
            ])->index();
            
            // Geographic restrictions
            $table->json('allowed_storage_regions')->comment('Where data can be stored at rest');
            $table->json('allowed_processing_regions')->comment('Where data can be processed');
            $table->json('allowed_backup_regions')->comment('Where backups can be stored');
            $table->json('prohibited_regions')->nullable();
            
            // Encryption requirements
            $table->json('encryption_requirements')->comment('Algorithm, key length, etc.');
            $table->boolean('encryption_at_rest_required')->default(true);
            $table->boolean('encryption_in_transit_required')->default(true);
            $table->boolean('encryption_in_use_required')->default(false);
            
            // Access controls
            $table->boolean('cross_border_transfer_approval_required')->default(false);
            $table->json('approval_authority')->nullable();
            $table->json('transfer_mechanisms')->nullable()->comment('Standard contractual clauses, BCR, etc.');
            
            // Retention policies
            $table->unsignedSmallInteger('minimum_retention_period_years');
            $table->unsignedSmallInteger('maximum_retention_period_years')->nullable();
            $table->enum('retention_basis', ['legal_requirement', 'business_need', 'consent_based'])->default('legal_requirement');
            
            // Deletion requirements
            $table->boolean('right_to_erasure_applicable')->default(true)->comment('GDPR Article 17');
            $table->json('erasure_exceptions')->nullable();
            $table->unsignedSmallInteger('erasure_response_time_days')->default(30);
            
            // Breach notification
            $table->unsignedSmallInteger('breach_notification_hours')->default(72)->comment('GDPR: 72 hours');
            $table->json('notification_authorities')->nullable();
            
            // Applicable laws
            $table->json('applicable_regulations')->comment('GDPR, HIPAA, CCPA, etc.');
            $table->text('regulation_summary')->nullable();
            $table->string('legal_reference_url', 512)->nullable();
            
            // Status
            $table->enum('status', ['active', 'under_review', 'superseded'])->default('active')->index();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable();
            
            // Audit
            $table->timestamps();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Composite unique constraint
            $table->unique(['region_code', 'data_category']);
        });

        /**
         * ========================================================================
         * SUPPORTING TABLES - Appointments, Communications, Documents
         * ========================================================================
         */
        
        /**DONE++
         * APPOINTMENTS - Scheduled visits
         */
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('appointment_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('provider_staff_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            
            // Appointment details
            $table->enum('appointment_type', [
                'new_patient_consultation',
                'followup_visit',
                'annual_physical',
                'procedure',
                'diagnostic_test',
                'therapy_session',
                'telehealth',
                'vaccination',
                'consultation'
            ])->index();
            
            // Timing
            $table->timestamp('scheduled_start_time')->index();
            $table->timestamp('scheduled_end_time');
            $table->unsignedSmallInteger('duration_minutes');
            
            // Reason
            $table->text('reason_for_visit')->nullable();
            $table->json('requested_services')->nullable();
            
            // Status
            $table->enum('status', [
                'scheduled',
                'confirmed',
                'checked_in',
                'in_progress',
                'completed',
                'no_show',
                'cancelled',
                'rescheduled'
            ])->default('scheduled')->index();
            
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Reminders
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Created visit linkage
            $table->unsignedBigInteger('created_visit_id')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('provider_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('created_visit_id')->references('id')->on('visits')->onDelete('set null');
            
            // Performance indexes
            $table->index(['facility_id', 'scheduled_start_time', 'status']);
            $table->index(['patient_id', 'scheduled_start_time']);
            $table->index(['provider_staff_id', 'scheduled_start_time']);
        });

        /**DONE++
         * CLINICAL_DOCUMENTS - Attached medical documents
         */
        Schema::create('clinical_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('document_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            
            // Document classification
            $table->enum('document_type', [
                'lab_report',
                'radiology_report',
                'pathology_report',
                'operative_note',
                'discharge_summary',
                'consultation_letter',
                'referral_letter',
                'consent_form',
                'advance_directive',
                'insurance_card',
                'identification',
                'medical_record_request',
                'other'
            ])->index();
            
            // File information
            $table->string('document_name', 300);
            $table->text('document_description')->nullable();
            $table->string('file_mime_type', 100);
            $table->string('file_extension', 10);
            $table->unsignedInteger('file_size_bytes');
            $table->string('file_storage_path', 512);
            $table->string('file_hash', 128)->comment('SHA-256 for integrity');
            
            // Metadata
            $table->date('document_date')->nullable()->index();
            $table->unsignedBigInteger('authored_by_staff_id')->nullable();
            $table->string('external_author', 200)->nullable();
            
            // Status
            $table->enum('status', ['active', 'superseded', 'entered_in_error'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('uploaded_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('set null');
            
            // Performance indexes
            $table->index(['patient_id', 'document_type', 'document_date']);
            $table->index(['visit_id', 'document_type']);
        });

      
        /**DONE++
         * ------------------------------------------------------------
         * CONVERSATIONS
         * ------------------------------------------------------------
         */
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_uuid')->unique()->index();

            $table->foreignId('facility_id')
                ->constrained('facilities')
                ->cascadeOnDelete();

            $table->enum('conversation_type', [
                'direct',
                'group',
                'broadcast',
                'system',
                'care_context'
            ])->index();

            // Optional clinical context
            $table->foreignId('visit_id')
                ->nullable()
                ->constrained('visits')
                ->nullOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->string('department_code', 50)->nullable()->index();
            $table->string('title', 255)->nullable();

            // Compliance & priority
            $table->boolean('contains_phi')->default(true)->index();
            $table->boolean('is_emergency')->default(false)->index();

            $table->enum('status', [
                'active',
                'archived',
                'locked'
            ])->default('active')->index();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['facility_id', 'conversation_type']);
        });

        /**DONE++
         * ------------------------------------------------------------
         * CONVERSATION PARTICIPANTS
         * ------------------------------------------------------------
         */
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->enum('participant_type', ['staff', 'patient']);
            $table->unsignedBigInteger('participant_id');

            $table->enum('role', [
                'owner',
                'moderator',
                'member',
                'read_only'
            ])->default('member');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_muted')->default(false);

            $table->timestamps();

            $table->unique(
                ['conversation_id', 'participant_type', 'participant_id'],
                'conversation_participant_unique'
            );

            $table->index(['participant_type', 'participant_id']);
        });

        /**DONE++
         * ------------------------------------------------------------
         * MESSAGES
         * ------------------------------------------------------------
         */
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_uuid')->unique()->index();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            // Sender
            $table->enum('sender_type', ['staff', 'patient', 'system']);
            $table->unsignedBigInteger('sender_id')->nullable();

            // Content
            $table->enum('message_type', [
                'text',
                'rich_text',
                'system_event',
                'clinical_note',
                'alert',
                'file',
                'image'
            ])->index();

            $table->longText('content_encrypted')->nullable();
            $table->string('content_hash', 64)->index();

            // Clinical flags
            $table->boolean('contains_phi')->default(true)->index();
            $table->boolean('is_clinical')->default(false)->index();
            $table->boolean('requires_acknowledgement')->default(false);

            // Threading
            $table->foreignId('parent_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            // Delivery
            $table->enum('delivery_status', [
                'pending',
                'sent',
                'delivered',
                'failed'
            ])->default('pending')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['conversation_id', 'created_at']);
        });

        /**DONE++
         * ------------------------------------------------------------
         * MESSAGE RECEIPTS
         * ------------------------------------------------------------
         */
        Schema::create('message_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->enum('recipient_type', ['staff', 'patient']);
            $table->unsignedBigInteger('recipient_id');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['message_id', 'recipient_type', 'recipient_id'],
                'message_recipient_unique'
            );

            $table->index(['recipient_type', 'recipient_id']);
        });

        /**DONE.
         * ------------------------------------------------------------
         * MESSAGE ATTACHMENTS
         * ------------------------------------------------------------
         */
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('attachment_uuid')->unique()->index();

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->enum('attachment_type', [
                'image',
                'pdf',
                'lab_result',
                'radiology_image',
                'audio',
                'video',
                'other'
            ])->index();

            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('storage_disk', 50);
            $table->string('storage_path', 512);

            $table->boolean('contains_phi')->default(true);
            $table->string('checksum', 64)->index();

            $table->timestamps();
        });



        // Add additional supporting tables as needed...
        
        /**
         * ========================================================================
         * DATABASE PERFORMANCE OPTIMIZATIONS
         * ========================================================================
         */
        
        // Enable PostgreSQL-specific optimizations (if using PostgreSQL)
        if (DB::getDriverName() === 'pgsql') {
            // Partial indexes for active records (PostgreSQL)
            DB::statement('CREATE INDEX idx_visits_active_only ON visits(facility_id, status, current_phase) WHERE status = \'active\'');
            DB::statement('CREATE INDEX idx_patients_active_only ON patients(id, status) WHERE status = \'active\'');
            DB::statement('CREATE INDEX idx_staff_active_only ON staff(id, employment_status) WHERE employment_status = \'active\'');
            
            // GiST indexes for JSON searches (PostgreSQL)
            DB::statement('CREATE INDEX idx_clinical_encounters_diagnosis_codes_gin ON clinical_encounters USING GIN (assessment_diagnosis_codes)');
            DB::statement('CREATE INDEX idx_prescriptions_diagnosis_codes_gin ON prescriptions USING GIN (diagnosis_codes)');
        }
        
        // MySQL-specific optimizations
        if (DB::getDriverName() === 'mysql') {
            // Fulltext indexes for search (MySQL)
            DB::statement('CREATE FULLTEXT INDEX idx_patients_search ON patients(medical_record_number_encrypted)');
            DB::statement('CREATE FULLTEXT INDEX idx_clinical_encounters_notes ON clinical_encounters(clinical_notes_free_text)');
        }
    }

    /**
     * Reverse the migrations - Drop all tables in correct order
     *
     * @return void
     */
    public function down()
    {
        // Drop in reverse order to respect foreign key constraints
        
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_receipts');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('clinical_documents');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('data_residency_rules');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('patient_visit_summary_views');
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
        Schema::dropIfExists('ai_assessments');
        Schema::dropIfExists('clinical_encounters');
        Schema::dropIfExists('visit_routes');
        Schema::dropIfExists('visit_actors');
        Schema::dropIfExists('visit_events');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('facility_staff_roles');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('staff_credentials');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('users');
    }
};