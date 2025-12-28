<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * INVOICE_LINE_ITEMS - Detailed billing transactions
     * Purpose: Immutable snapshot of services rendered
     */
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('line_item_uuid')->unique()->index();
            $table->unsignedBigInteger('billing_cycle_id')->index();
            $table->unsignedBigInteger('visit_id')->index();
            
            // Service identification (frozen snapshot)
            $table->unsignedBigInteger('service_version_id')->index();
            $table->json('service_version_snapshot')->comment('Frozen pricing/terms at time of service');
            $table->string('service_code', 50)->index();
            $table->string('service_description', 500);
            
            // Quantity & pricing
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit_of_measure', 50)->default('each');
            $table->decimal('unit_price_at_time', 10, 2);
            $table->decimal('line_total_amount', 12, 2);
            
            // Discounts & adjustments
            $table->decimal('applied_discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('adjustment_amount', 10, 2)->default(0);
            $table->text('adjustment_reason')->nullable();
            $table->decimal('net_amount', 12, 2);
            
            // Service delivery context
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('staff_performed_id')->nullable()->index();
            $table->timestamp('service_performed_at')->index();
            $table->unsignedSmallInteger('service_duration_minutes')->nullable();
            
            // Clinical justification
            $table->json('diagnosis_codes')->nullable()->comment('ICD-10 codes justifying service');
            $table->text('medical_necessity_notes')->nullable();
            $table->json('modifier_codes')->nullable()->comment('CPT modifiers applied');
            
            // Insurance & billing codes
            $table->string('revenue_code', 20)->nullable()->comment('UB-04 revenue code');
            $table->string('procedure_code', 20)->nullable()->comment('CPT/HCPCS code');
            $table->json('insurance_specific_codes')->nullable();
            
            // Approval & authorization
            $table->string('preauthorization_number', 100)->nullable();
            $table->boolean('requires_review')->default(false);
            $table->boolean('coding_reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by_staff_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            // Status
            $table->enum('line_item_status', [
                'pending',
                'approved',
                'billed',
                'paid',
                'denied',
                'adjusted',
                'written_off'
            ])->default('pending')->index();
            
            // Audit & integrity
            $table->string('audit_trail_hash', 128)->comment('SHA-256 for tamper detection');
            $table->timestamps();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('billing_cycle_id')->references('id')->on('billing_cycles')->onDelete('cascade');
            $table->foreign('service_version_id')->references('id')->on('service_versions')->onDelete('restrict');
            $table->foreign('staff_performed_id')->references('id')->on('staff')->onDelete('set null');
            
            // Performance indexes
            $table->index(['billing_cycle_id', 'service_performed_at']);
            $table->index(['service_code', 'service_performed_at']);
            $table->index(['staff_performed_id', 'service_performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};