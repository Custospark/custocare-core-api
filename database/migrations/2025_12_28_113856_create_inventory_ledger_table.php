<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates the inventory_ledger table for immutable double-entry inventory accounting.
     * This table acts as an append-only transaction log for all inventory movements.
     */
    public function up(): void
    {
        Schema::create('inventory_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('inventory_item_id')->index();
            
            // Transaction details
            $table->enum('transaction_type', [
                'purchase',
                'adjustment_increase',
                'adjustment_decrease',
                'consumption_visit',
                'consumption_waste',
                'return_to_supplier',
                'transfer_in',
                'transfer_out',
                'cycle_count',
                'expired',
                'damaged',
                'stolen',
                'recalled'
            ])->index();
            
            // Quantity tracking (double-entry: always shows net change)
            $table->decimal('quantity_change', 10, 2)->comment('Positive=in, Negative=out');
            $table->decimal('balance_after_transaction', 10, 2)->index();
            $table->string('unit_of_measure', 50);
            
            // Lot & serial tracking
            $table->string('lot_number', 100)->nullable()->index();
            $table->string('serial_number', 100)->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->date('manufacture_date')->nullable();
            
            // Financial tracking
            $table->decimal('unit_cost_at_transaction', 10, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            
            // Context & linkage
            $table->unsignedBigInteger('reference_visit_id')->nullable()->index();
            $table->unsignedBigInteger('reference_prescription_id')->nullable()->index();
            $table->unsignedBigInteger('reference_purchase_order_id')->nullable();
            $table->unsignedBigInteger('transfer_from_facility_id')->nullable();
            $table->unsignedBigInteger('transfer_to_facility_id')->nullable();
            
            // Reason & documentation
            $table->enum('transaction_cause', [
                'manual_entry',
                'system_automated',
                'physical_count',
                'reconciliation',
                'patient_use',
                'procedural_use',
                'administrative'
            ])->index();
            
            $table->text('transaction_notes')->nullable();
            $table->string('reference_document_number', 100)->nullable();
            
            // Approval & verification
            $table->unsignedBigInteger('performed_by_staff_id')->nullable()->index();
            $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            // Storage location
            $table->string('storage_location', 200)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            
            // Audit & immutability
            $table->timestamp('transaction_timestamp')->index();
            $table->timestamps(); // Use timestamps() instead of separate created_at
            
            $table->string('transaction_hash', 128)->comment('SHA-256 for integrity');
            $table->json('metadata')->nullable();
            
            // Foreign keys
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('restrict');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('reference_visit_id')->references('id')->on('visits')->onDelete('set null');
            
            // Performance indexes (critical for inventory queries)
            $table->index(['facility_id', 'inventory_item_id', 'transaction_timestamp'],'fac_inventory_item_trans_timeestamp_unique');
            $table->index(['lot_number', 'expiry_date']);
            $table->index(['transaction_type', 'transaction_timestamp']);
            $table->index(['facility_id', 'transaction_type', 'created_at'],'fac_transact_type_created_at_unque');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger');
    }
};