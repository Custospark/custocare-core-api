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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('visit_uuid')->unique()->index()->comment('Public-facing identifier');
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('assigned_staff_id')->nullable()->index();
            
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
            $table->timestamp('assigned_at')->nullable();
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
            $table->foreign('assigned_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('current_department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('referring_facility_id')->references('id')->on('facilities')->onDelete('set null');
            $table->foreign('created_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('discharged_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('referring_provider_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('followup_provider_staff_id')->references('id')->on('staff')->onDelete('set null');
            
            // Critical performance indexes (optimized for operational queries)
            $table->index(['facility_id', 'status', 'current_phase']);
            $table->index(['facility_id', 'arrived_at', 'status']);
            $table->index(['patient_id', 'arrived_at']);
            $table->index(['current_department_id', 'waiting_since', 'status']);
            $table->index(['acuity_score', 'waiting_since', 'status']);
            $table->index(['discharged_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('visits');
    }
};