<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the facilities table with healthcare facility registry structure.
     * Reference data optimized for CDN distribution and caching.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_uuid')->unique()->index();
            
            // Facility identification
            $table->string('facility_code', 50)->unique()->index();
            $table->string('facility_name', 200);
            $table->string('legal_entity_name', 200);
            $table->string('tax_id_encrypted', 512)->nullable();
            
            // Facility classification
            $table->enum('nature_of_facility', [
                "government",
                "private",
                "faith_based",
                "ngo",
                "military",
                "academic",
                "public_private_partnership"
            ])->index();
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
                'telehealth_hub',
                'laboratory',
                'pharmacy'
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
            $table->string('country_code', 2)->index();
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
            $table->string('data_residency_region', 10)->nullable()->index();
            $table->string('primary_database_shard', 50)->nullable()->index();
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
            
            // Foreign key constraints
            $table->foreign('parent_organization_id')
                ->references('id')
                ->on('facilities')
                ->nullOnDelete();
                
            $table->foreign('created_by_staff_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
                
            $table->foreign('updated_by_staff_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};