<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facility_staff_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('assignment_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('staff_id')->index();
            
            // Role definition
            $table->enum('role_code', [
            'physician',
            'surgeon',
            'anesthesiologist',
            'nurse',
            'nurse_manager',
            'pharmacist',
            'pharmacy_technician',
            'radiologist',
            'radiology_technician',
            'laboratory_scientist',
            'respiratory_therapist',
            'physical_therapist',
            'occupational_therapist',
            'social_worker',
            'case_manager',
            'medical_assistant',
            'receptionist',
            'facility_administrator',
            'department_manager',
            'quality_coordinator',
            'infection_control',
            'it_support'
        ])->index();

            
            $table->json('department_ids')->nullable()->comment('Departments within facility where staff works');
            $table->boolean('is_primary_facility')->default(false);
            
            // Privileges at this facility
            $table->json('privileges_bitmask')->nullable()->comment('Bitwise flags for specific privileges');
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
            $table->unique(['facility_id','staff_id','role_code'], 'fsr_facility_staff_role_eff_from_unique');
            
            // Performance indexes
            $table->index(['staff_id', 'is_primary_facility']);
            $table->index(['effective_to', 'assignment_status']); // For cleanup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_staff_roles');
    }
};