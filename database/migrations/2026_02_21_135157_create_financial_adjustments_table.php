<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('adjustment_uuid')->unique();
            
            // References
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('billing_cycle_id');
            $table->unsignedBigInteger('visit_id');
            $table->unsignedBigInteger('patient_id');
            
            // Adjustment details
            $table->enum('adjustment_type', [
                'full_refund',
                'partial_refund', 
                'void_transaction',
                'line_item_refund',
                'payment_reversal'
            ]);
            
            $table->enum('adjustment_reason', [
                'billing_error',
                'service_not_rendered',
                'duplicate_charge',
                'patient_request',
                'insurance_denial',
                'administrative_correction',
                'pricing_error',
                'cancelled_service',
                'other'
            ]);
            
            $table->text('reason_notes')->nullable();
            
            // Financial impact
            $table->decimal('original_amount', 12, 2); // Original billing amount
            $table->decimal('adjustment_amount', 12, 2); // Amount being refunded/voided
            $table->decimal('remaining_amount', 12, 2); // Amount still valid after adjustment
            
            // Payment breakdown for refund
            $table->decimal('patient_refund_amount', 10, 2)->default(0);
            $table->decimal('insurance_refund_amount', 10, 2)->default(0);
            
            // Refund method tracking
            $table->json('refund_methods')->nullable(); // How refund is being issued
            
            // Line item specifics (for partial refunds)
            $table->json('affected_line_items')->nullable(); // Array of line_item_ids with amounts
            
            // Inventory impact
            $table->boolean('restore_inventory')->default(false);
            $table->json('inventory_restored')->nullable(); // Track what was restored
            
            // Status tracking
            $table->enum('status', [
                'pending',
                'approved',
                'processing',
                'completed',
                'rejected',
                'failed'
            ])->default('pending');
            
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Approval workflow
            $table->unsignedBigInteger('requested_by_staff_id');
            $table->unsignedBigInteger('approved_by_staff_id')->nullable();
            
            // Audit trail
            $table->string('reference_number', 100)->unique(); // REF-xxxxx
            $table->json('original_billing_snapshot'); // Full snapshot of original billing
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('billing_cycle_id')->references('id')->on('billing_cycles');
            $table->foreign('visit_id')->references('id')->on('visits');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('requested_by_staff_id')->references('id')->on('staff');
            $table->foreign('approved_by_staff_id')->references('id')->on('staff');
            
            // Indexes
            $table->index(['facility_id', 'created_at']);
            $table->index(['billing_cycle_id', 'adjustment_type']);
            $table->index('status');
            $table->index('adjustment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');
    }
};