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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('staff_uuid')->unique()->index();
            $table->unsignedBigInteger('user_id')->unique();
            
            // Professional identification
            $table->string('employee_id', 50)->unique()->index();
            $table->string('professional_title', 100)->nullable();
            $table->string('professional_license_number_encrypted', 512)->nullable();
            $table->string('professional_license_number_hash', 128)->nullable()->unique();
            $table->string('license_issuing_state', 50)->nullable();
            $table->string('license_issuing_country', 3)->default('USA');
            $table->date('license_expiry_date')->nullable()->index();
            
            // Credentials & certifications
            $table->json('specialization_codes')->nullable()->comment('NUCC Healthcare Provider Taxonomy codes');
            $table->json('board_certifications')->nullable();
            $table->json('additional_certifications')->nullable();
            $table->string('npi_number', 20)->nullable()->unique()->comment('National Provider Identifier');
            $table->string('dea_number_encrypted', 512)->nullable()->comment('Drug Enforcement Administration');
            $table->date('dea_expiry_date')->nullable();
            
            // Employment status
            $table->enum('employment_status', [
                'employed',
                'suspended',
                'unemployed',
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
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reports_to_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['employment_status', 'global_role_level']);
            $table->index(['license_expiry_date', 'employment_status']);
            $table->index(['dea_expiry_date', 'employment_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('staff');
    }
};