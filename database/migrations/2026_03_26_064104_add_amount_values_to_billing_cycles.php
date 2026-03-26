<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('billing_cycles', function (Blueprint $table) {
            // Direct snapshots from frontend / computed billing summary
            $table->decimal('subtotal_amount', 12, 2)->default(0.00)->after('days_in_cycle');
            $table->decimal('taxable_amount', 12, 2)->default(0.00)->after('discount_applied');
            $table->decimal('grand_total_amount', 12, 2)->default(0.00)->after('net_amount');
            $table->decimal('total_paid_amount', 12, 2)->default(0.00)->after('patient_payment_received');
            $table->decimal('balance_amount', 12, 2)->default(0.00)->after('total_paid_amount');
        });

        // Add "pending" to billing_status because zero payment should not become partially_paid
        DB::statement("
            ALTER TABLE billing_cycles
            MODIFY billing_status ENUM(
                'draft',
                'pending',
                'pending_review',
                'pending_submission',
                'submitted_to_insurance',
                'partially_paid',
                'paid_in_full',
                'payment_plan',
                'collections',
                'disputed',
                'written_off',
                'charity_care',
                'partially_refunded',
                'fully_refunded'
            ) NOT NULL DEFAULT 'draft'
        ");

        // Backfill the new snapshot fields from existing data
        DB::statement("
            UPDATE billing_cycles
            SET
                subtotal_amount = COALESCE(total_amount_charged, 0.00),
                taxable_amount = GREATEST(
                    COALESCE(total_amount_charged, 0.00) - COALESCE(discount_applied, 0.00),
                    0.00
                ),
                grand_total_amount = COALESCE(net_amount, 0.00),
                total_paid_amount = COALESCE(patient_payment_received, 0.00) + COALESCE(insurance_payment_received, 0.00),
                balance_amount = GREATEST(
                    COALESCE(net_amount, 0.00) - (
                        COALESCE(patient_payment_received, 0.00) + COALESCE(insurance_payment_received, 0.00)
                    ),
                    0.00
                )
        ");

        // Correct old wrongly-set statuses:
        // - paid_in_full when balance is zero
        // - partially_paid only if amount paid > 0
        // - pending when amount paid is zero
        DB::statement("
            UPDATE billing_cycles
            SET billing_status = CASE
                WHEN GREATEST(
                    COALESCE(net_amount, 0.00) - (
                        COALESCE(patient_payment_received, 0.00) + COALESCE(insurance_payment_received, 0.00)
                    ),
                    0.00
                ) < 0.01 THEN 'paid_in_full'
                WHEN (COALESCE(patient_payment_received, 0.00) + COALESCE(insurance_payment_received, 0.00)) > 0 THEN 'partially_paid'
                WHEN billing_status = 'partially_paid' THEN 'pending'
                ELSE billing_status
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert pending back before removing it from the enum
        DB::statement("
            UPDATE billing_cycles
            SET billing_status = 'partially_paid'
            WHERE billing_status = 'pending'
        ");

        DB::statement("
            ALTER TABLE billing_cycles
            MODIFY billing_status ENUM(
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
                'charity_care',
                'partially_refunded',
                'fully_refunded'
            ) NOT NULL DEFAULT 'draft'
        ");

        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount',
                'taxable_amount',
                'grand_total_amount',
                'total_paid_amount',
                'balance_amount',
            ]);
        });
    }
};
