<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations for medication dispenses - Pharmacy fulfillment records
     * 
     * This table implements the 4-eyes principle for medication dispensing with 
     * comprehensive safety checks, patient education tracking, and audit trails.
     */
    public function up(): void
    {
        Schema::create('medication_dispenses', function (Blueprint $table) {
            // Primary identifier
            $table->id();
            
            // UUID for external systems and security
            $table->uuid('dispense_uuid')->unique()->index();
            
            // Context references
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->nullable()->index();
            $table->unsignedBigInteger('prescription_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Dispense details (frozen snapshot to preserve historical accuracy)
            $table->json('prescription_details_snapshot')->comment('Frozen prescription at time of dispense');
            $table->unsignedBigInteger('dispensed_inventory_ledger_id')->nullable();
            
            // Quantity dispensed with precise tracking
            $table->decimal('quantity_dispensed', 8, 2);
            $table->string('quantity_unit', 50);
            $table->string('lot_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            
            // Staff verification (4-eyes principle implementation)
            $table->unsignedBigInteger('dispensed_by_staff_id')->index();
            $table->timestamp('dispensed_at')->index();
            $table->unsignedBigInteger('checked_by_staff_id')->nullable()->comment('Pharmacist verification');
            $table->timestamp('checked_at')->nullable();
            $table->text('pharmacist_notes')->nullable();
            
            // Patient education & counseling (quality metrics)
            $table->boolean('patient_counseling_provided')->default(false);
            $table->boolean('medication_guide_provided')->default(false);
            $table->text('patient_education_topics')->nullable();
            $table->text('patient_questions_addressed')->nullable();
            
            // Instructions for proper usage
            $table->text('dispensed_instructions')->nullable();
            $table->text('followup_instructions')->nullable();
            $table->json('warning_labels_applied')->nullable();
            
            // Safety checks performed (compliance requirement)
            $table->json('safety_checks_performed')->comment('Allergy, interaction, duplicate therapy');
            $table->boolean('all_safety_checks_passed')->default(true);
            $table->json('safety_check_overrides')->nullable();
            $table->text('override_justification')->nullable();
            
            // Delivery method tracking
            $table->enum('delivery_method', [
                'pickup_in_person',
                'mail_order',
                'delivery_service',
                'administered_in_facility',
                'sent_to_home_health'
            ])->nullable();
            
            // Pickup verification
            $table->timestamp('picked_up_at')->nullable();
            $table->string('picked_up_by_name', 200)->nullable();
            $table->string('pickup_id_verified', 100)->nullable();
            
            // Billing and financial tracking
            $table->decimal('copay_collected', 8, 2)->nullable();
            $table->decimal('total_cost_to_patient', 10, 2)->nullable();
            $table->decimal('insurance_payment', 10, 2)->nullable();
            
            // Lifecycle status
            $table->enum('status', ['dispensed', 'not_picked_up', 'returned', 'destroyed'])->default('dispensed')->index();
            
            // Audit trail
            $table->timestamps();
            $table->json('metadata')->nullable();
            
            // Foreign key constraints with appropriate cascade actions
            $table->foreign('prescription_id')->references('id')->on('prescriptions')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('dispensed_by_staff_id')->references('id')->on('staff')->onDelete('restrict');
            $table->foreign('checked_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('dispensed_inventory_ledger_id')->references('id')->on('inventory_ledger')->onDelete('set null');
            
            // Performance-optimized composite indexes
            $table->index(['prescription_id', 'dispensed_at']);
            $table->index(['patient_id', 'dispensed_at']);
            $table->index(['facility_id', 'dispensed_at']);
            
            // Audit index for compliance reporting
            $table->index(['status', 'dispensed_at']);
        });
        
        // Add database-level constraint for dispense timestamp
        DB::statement('ALTER TABLE medication_dispenses ADD CONSTRAINT chk_dispense_timestamp CHECK (dispensed_at <= CURRENT_TIMESTAMP)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_dispenses');
    }
};