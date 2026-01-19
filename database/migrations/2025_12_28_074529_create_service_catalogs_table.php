<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->uuid('service_uuid')->unique()->index();
            // Service identification
            $table->string('service_code', 50)->index()->comment('CPT, ICD, HCPCS, or local code');
            $table->enum('code_system', [
                'cpt',              // Current Procedural Terminology
                'hcpcs',           // Healthcare Common Procedure Coding System
                'icd_10_pcs',      // ICD-10 Procedure Coding System
                'cdt',             // Dental codes
                'local_custom'     // Facility-specific codes
            ])->default('local_custom')->index();
            
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
            $table->json('regulatory_approval_status')->nullable()->comment('FDA, state licensing, etc.');
            $table->json('required_certifications')->nullable();
            $table->json('minimum_required_credentials')->nullable()->comment('Staff qualifications needed');
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
            $table->string('applicable_region', 10)->nullable()->comment('US, EU, APAC, etc.');
            $table->json('approved_countries')->nullable();
            $table->json('state_specific_regulations')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'deprecated', 'under_review'])->default('active')->index();
            $table->date('effective_from')
                ->default(DB::raw('CURRENT_DATE'))
                ->index();
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_catalogs');
    }
};