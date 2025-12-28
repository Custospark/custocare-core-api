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
            
            // Composite unique constraint for certain scenarios
            $table->unique(['patient_id', 'medication_name', 'prescribed_at'], 'unique_prescription_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};