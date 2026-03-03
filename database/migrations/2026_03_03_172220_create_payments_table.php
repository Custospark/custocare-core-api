<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS
 *
 * Records every payment attempt against a facility's subscription.
 * Admin manually approves/rejects payments in the manual billing phase.
 * Gateway fields are populated when a payment gateway is integrated later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payments');
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // ── Relations ─────────────────────────────────────────────────
            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')
                  ->references('id')->on('subscriptions')
                  ->onDelete('restrict');

            $table->unsignedBigInteger('facility_id')
                  ->comment('Denormalized for direct facility payment queries');
            $table->foreign('facility_id')
                  ->references('id')->on('facilities')
                  ->onDelete('restrict');

            // ── Amount ────────────────────────────────────────────────────
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('UGX')
                  ->comment('ISO-4217 currency code');

            // ── Classification ────────────────────────────────────────────
            $table->enum('method', [
                'mobile_money',
                'bank_transfer',
                'cash',
                'gateway',          // reserved for future gateway integration
            ])->comment('Payment channel used');

            $table->enum('payment_type', [
                'onboarding',       // one-time setup fee
                'subscription',     // initial subscription payment
                'renewal',          // monthly renewal
            ])->comment('What this payment covers');

            // ── Status ────────────────────────────────────────────────────
            $table->enum('status', [
                'pending',          // awaiting admin review
                'approved',         // admin confirmed; triggers subscription change
                'rejected',         // admin rejected
                'refunded',         // money returned
            ])->default('pending')->index();

            // ── Manual payment evidence ───────────────────────────────────
            $table->string('transaction_reference', 255)->nullable()
                  ->comment('Mobile money / bank reference number provided by facility');
            $table->string('receipt_path', 512)->nullable()
                  ->comment('Path to uploaded receipt image or PDF');
            $table->text('receipt_notes')->nullable()
                  ->comment('Additional notes from the facility about the payment');

            // ── Timestamps ────────────────────────────────────────────────
            $table->timestamp('paid_at')->nullable()
                  ->comment('When the facility claims payment was made');

            // ── Admin approval audit ──────────────────────────────────────
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_staff_id')->nullable()
                  ->comment('Staff member who approved or rejected this payment');
            $table->foreign('approved_by_staff_id')
                  ->references('id')->on('staff')
                  ->nullOnDelete();
            $table->text('rejection_reason')->nullable()
                  ->comment('Required when status = rejected');

            // ── Gateway integration (prepared, not yet active) ────────────
            $table->string('gateway_name', 50)->nullable()
                  ->comment('e.g. flutterwave, mtn_momo, airtel_money');
            $table->string('gateway_transaction_id', 255)->nullable()
                  ->comment('Transaction ID from the payment gateway');
            $table->json('gateway_response')->nullable()
                  ->comment('Full gateway response payload for audit/debugging');

            $table->json('metadata')->nullable();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            $table->index(['facility_id', 'status']);
            $table->index(['subscription_id', 'status']);
            $table->index('paid_at');
            $table->index('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
