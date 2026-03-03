<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUBSCRIPTIONS
 *
 * One active subscription per facility at any given time.
 * Tied to facilities, not users or apps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // ── Core relations ────────────────────────────────────────────
            $table->unsignedBigInteger('facility_id')
                  ->comment('FK → facilities.id; subscription belongs to a facility');
            $table->foreign('facility_id')
                  ->references('id')->on('facilities')
                  ->onDelete('restrict'); // Never silently delete a subscription

            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id')
                  ->references('id')->on('plans')
                  ->onDelete('restrict');

            // ── Status lifecycle ──────────────────────────────────────────
            $table->enum('status', [
                'trial',
                'active',
                'past_due',
                'suspended',
                'cancelled',
            ])->default('trial')->index()
              ->comment('trial→active→past_due→suspended|cancelled');

            // ── Timeline ──────────────────────────────────────────────────
            $table->timestamp('trial_ends_at')->nullable()
                  ->comment('When the free trial expires');
            $table->timestamp('starts_at')
                  ->comment('Billing period start; set when subscription created');
            $table->timestamp('ends_at')
                  ->comment('Billing period end; starts_at + 1 month');
            $table->timestamp('next_billing_date')
                  ->comment('Date payment is next due; equals ends_at');
            $table->timestamp('grace_period_ends_at')->nullable()
                  ->comment('Deadline before suspension; next_billing_date + 7 days');

            // ── Deactivation timestamps ───────────────────────────────────
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // ── Approval audit ────────────────────────────────────────────
            $table->timestamp('approved_at')->nullable()
                  ->comment('When admin first activated this subscription');
            $table->unsignedBigInteger('approved_by_staff_id')->nullable()
                  ->comment('Staff member who approved the initial activation');
            $table->foreign('approved_by_staff_id')
                  ->references('id')->on('staff')
                  ->nullOnDelete();

            // ── Onboarding fee flag ───────────────────────────────────────
            $table->boolean('onboarding_fee_paid')->default(false)
                  ->comment('True once the one-time onboarding payment is approved');

            $table->text('notes')->nullable()
                  ->comment('Admin notes about this subscription');
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────
            $table->index(['facility_id', 'status']);
            $table->index('next_billing_date');
            $table->index('grace_period_ends_at');
            $table->index('trial_ends_at');

            // ── One active subscription per facility ──────────────────────
            // Enforced at service layer; DB unique removed to allow history.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
