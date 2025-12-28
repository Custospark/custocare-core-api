<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * SERVICE_VERSIONS - Versioned pricing & terms for historical billing accuracy
     */
    public function up(): void
    {
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
            $table->foreign('service_catalog_id')
                  ->references('id')
                  ->on('service_catalogs')
                  ->onDelete('cascade');
            
            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['service_catalog_id', 'facility_id', 'version_number'], 
                          'unique_version_per_service_facility');
            
            // Performance indexes
            $table->index(['service_catalog_id', 'valid_from', 'valid_to'], 
                         'idx_service_validity');
            $table->index(['facility_id', 'is_current'], 
                         'idx_facility_current');
            
            // Additional indexes for common queries
            $table->index(['is_current', 'valid_from']);
            $table->index(['is_billable', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_versions');
    }
};