<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BILLING_CYCLES - Financial period aggregation
     * Shard Strategy: Sharded by (facility_id, visit_id)
     */
    public function up(): void
    {
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->uuid('billing_cycle_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            
            // Cycle definition
            $table->enum('cycle_type', [
                'visit_based',          // Single visit billing
                'admission_discharge',  // Inpatient stay
                'daily_inpatient',     // Daily charges
                'weekly_inpatient',    // Weekly billing
                'procedure_based',     // Single procedure
                'bundled_payment',     // DRG or bundled
                'subscription'         // Ongoing care plan
            ])->index();
            
            // Time period
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->nullable()->index();
            $table->unsignedSmallInteger('days_in_cycle')->nullable();
            
            // Financial summary
            $table->decimal('total_amount_charged', 12, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            
            // Insurance processing
            $table->string('primary_insurance_claim_number', 100)->nullable();
            $table->decimal('insurance_covered_amount', 10, 2)->default(0);
            $table->decimal('insurance_adjustment_amount', 10, 2)->default(0);
            $table->decimal('insurance_payment_received', 10, 2)->default(0);
            $table->timestamp('insurance_claim_submitted_at')->nullable();
            $table->timestamp('insurance_payment_received_at')->nullable();
            
            // Patient responsibility
            $table->decimal('patient_responsibility_amount', 10, 2)->default(0);
            $table->decimal('patient_copay_amount', 10, 2)->default(0);
            $table->decimal('patient_deductible_amount', 10, 2)->default(0);
            $table->decimal('patient_coinsurance_amount', 10, 2)->default(0);
            $table->decimal('patient_payment_received', 10, 2)->default(0);
            
            // Discounts & adjustments
            $table->decimal('discount_applied', 10, 2)->default(0);
            $table->string('discount_reason', 200)->nullable();
            $table->decimal('contractual_adjustment', 10, 2)->default(0);
            $table->decimal('charity_care_adjustment', 10, 2)->default(0);
            $table->decimal('bad_debt_adjustment', 10, 2)->default(0);
            
            // Tax & fees
            $table->json('tax_details')->nullable();
            $table->decimal('total_tax_amount', 10, 2)->default(0);
            
            // Billing status
            $table->enum('billing_status', [
                'draft',
                'pending_review',
                'pending_submission',
                'submitted_to_insurance',
                'partially_paid',
                'paid_in_full',
                'payment_plan',
                'collections',
                'disputed',
                'written_off',
                'charity_care'
            ])->default('draft')->index();
            
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('payment_due_date')->nullable();
            $table->unsignedSmallInteger('days_outstanding')->nullable();
            
            // Collections & follow-up
            $table->unsignedTinyInteger('statement_count')->default(0);
            $table->timestamp('last_statement_sent_at')->nullable();
            $table->timestamp('sent_to_collections_at')->nullable();
            $table->string('collections_agency', 200)->nullable();
            
            // Dispute management
            $table->boolean('is_disputed')->default(false);
            $table->text('dispute_reason')->nullable();
            $table->timestamp('dispute_opened_at')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            
            // Audit trail
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            
            // Performance indexes
            $table->index(['facility_id', 'billing_status', 'period_start']);
            $table->index(['patient_id', 'billing_status']);
            $table->index(['billing_status', 'payment_due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_cycles');
    }
};