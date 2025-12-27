<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates STAFF_CREDENTIALS table for immutable audit trail of credentialing events
     * Shard Strategy: Sharded by staff_id
     */
    public function up(): void
    {
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
            
            // Self-referencing foreign key for superseded credentials
            $table->foreign('superseded_by_credential_id')->references('id')->on('staff_credentials')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_credentials');
    }
};