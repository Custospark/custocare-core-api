<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * DATA_RESIDENCY_RULES - Regional compliance policies
     * Purpose: Enforce GDPR, HIPAA, and local data protection laws
     */
    public function up(): void
    {
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
            
            // Foreign key constraints
            $table->foreign('created_by_staff_id')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // Additional indexes for performance
            $table->index(['status', 'effective_from']);
            $table->index(['data_category', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_residency_rules');
    }
};