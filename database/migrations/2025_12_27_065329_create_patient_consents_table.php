<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('consent_uuid')->unique()->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Consent classification
            $table->enum('consent_type', [
                'treatment',
                'procedures',
                'anesthesia',
                'blood_transfusion',
                'research',
                'data_sharing',
                'marketing',
                'photography',
                'teaching',
                'organ_donation',
                'release_of_info'
            ])->index();
            
            // Scope definition
            $table->json('scope_facility_ids')->nullable()->comment('NULL = all facilities');
            $table->json('scope_department_ids')->nullable();
            $table->json('scope_staff_ids')->nullable()->comment('Specific providers only');
            $table->json('scope_service_categories')->nullable();
            $table->text('scope_limitations')->nullable()->comment('Free-text limitations');
            
            // Legal basis (GDPR compliance)
            $table->enum('legal_basis', [
                'explicit_consent',
                'contractual',
                'legal_obligation',
                'vital_interests',
                'legitimate_interest'
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_consents');
    }
};