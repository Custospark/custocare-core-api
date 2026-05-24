<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PaymentType;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService implements SubscriptionServiceInterface
{
    /** Days of grace period after the billing date passes. */
    private const GRACE_PERIOD_DAYS = 7;

    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly FacilityStaffRoleModuleSyncServiceInterface $moduleSyncService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new facility subscription, starting in trial status.
     * The subscription becomes active only once a payment is admin-approved.
     *
     * @throws \Exception If the facility already has a non-cancelled subscription.
     */
    public function createSubscription(Facility $facility, Plan $plan, array $options = []): Subscription
    {
        return DB::transaction(function () use ($facility, $plan, $options) {

            // ── Guard: one active/trial/pending payment subscription per facility ──
            $existing = $this->subscriptionRepo->findByFacility($facility->id);

            if ($existing && ! in_array($existing->status, [
                SubscriptionStatus::CANCELLED,
                SubscriptionStatus::SUSPENDED,
            ])) {
                // If there's already a pending payment, just update the plan
                $pendingPayments = $this->paymentRepo->findPendingByFacility($facility->id);
                if (!empty($pendingPayments) && $existing->status === SubscriptionStatus::TRIAL) {
                    $updated = $this->subscriptionRepo->update($existing, [
                        'plan_id' => $plan->id,
                    ]);
                    Log::info('[Billing] Subscription plan updated (pending payment exists)', [
                        'subscription_id' => $existing->id,
                        'new_plan'        => $plan->name,
                    ]);
                    return $updated;
                }

                throw new \Exception(
                    "Facility already has a {$existing->status->value} subscription (ID #{$existing->id}). " .
                    'Cancel or wait for it to be suspended before creating a new one.',
                    422
                );
            }

            $now = Carbon::now();

            // ── Check if facility has ever had a trial before (anti-abuse) ──
            $hasUsedTrialBefore = $this->subscriptionRepo->hasEverHadTrial($facility->id);

            $status = $hasUsedTrialBefore
                ? SubscriptionStatus::PAST_DUE->value
                : SubscriptionStatus::TRIAL->value;

            $trialEndsAt = $hasUsedTrialBefore ? null : $now->copy()->addDays($plan->trial_days);

            // ── Build the subscription payload ────────────────────────────
            $subscription = $this->subscriptionRepo->create([
                'facility_id'        => $facility->id,
                'plan_id'            => $plan->id,
                'status'             => $status,
                'trial_ends_at'      => $trialEndsAt,
                'starts_at'          => $now,
                'ends_at'            => $now->copy()->addMonth(),
                'next_billing_date'  => $now->copy()->addMonth(),
                'onboarding_fee_paid' => false,
                'notes'              => $options['notes'] ?? null,
                'metadata'           => $options['metadata'] ?? null,
            ]);

            Log::info('[Billing] Subscription created', [
                'facility_id'     => $facility->id,
                'subscription_id' => $subscription->id,
                'plan'            => $plan->name,
                'status'          => $status,
            ]);

            return $subscription;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Activation (triggered when admin approves a payment)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Activate a subscription after an initial payment is admin-confirmed.
     * Called by PaymentService::approvePayment() for subscription/onboarding payments.
     */
    public function activateSubscription(
        Subscription $subscription,
        Payment $payment,
        ?User $approvedBy
    ): Subscription {
        return DB::transaction(function () use ($subscription, $payment, $approvedBy) {

            $now = Carbon::now();

            $updated = $this->subscriptionRepo->update($subscription, [
                'status'              => SubscriptionStatus::ACTIVE->value,
                'starts_at'           => $now,
                'ends_at'             => $now->addMonth(),
                'next_billing_date'   => $now->addMonth(),
                'grace_period_ends_at' => null,
                'suspended_at'        => null,
                'approved_at'         => $now,
                'approved_by_user_id' => $approvedBy ? $approvedBy->id : null,
                'onboarding_fee_paid' => $payment->payment_type === PaymentType::ONBOARDING
                    ? true
                    : $subscription->onboarding_fee_paid,
            ]);

            Log::info('[Billing] Subscription activated', [
                'subscription_id' => $updated->id,
                'facility_id'     => $updated->facility_id,
                'approved_by'     => $approvedBy->id,
            ]);

            $this->moduleSyncService->syncForSubscription($updated->fresh(['plan']));

            return $updated;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Renewal (triggered when admin approves a renewal payment)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Renew an existing subscription by one billing cycle.
     * Extends from the current ends_at to preserve full period continuity.
     */
    public function renewSubscription(
        Subscription $subscription,
        Payment $payment,
        ?User $approvedBy
    ): Subscription {
        return DB::transaction(function () use ($subscription, $payment, $approvedBy) {

            // Extend from current ends_at to not penalise early renewal
            $newEndsAt = $subscription->ends_at->isFuture()
                ? $subscription->ends_at->copy()->addMonth()
                : Carbon::now()->addMonth();

            $updated = $this->subscriptionRepo->update($subscription, [
                'status'               => SubscriptionStatus::ACTIVE->value,
                'ends_at'              => $newEndsAt,
                'next_billing_date'    => $newEndsAt,
                'grace_period_ends_at' => null,
                'suspended_at'         => null,
            ]);

            Log::info('[Billing] Subscription renewed', [
                'subscription_id' => $updated->id,
                'new_ends_at'     => $newEndsAt->toDateTimeString(),
                'approved_by'     =>$approvedBy? $approvedBy->id: null,
            ]);

            $this->moduleSyncService->syncForSubscription($updated->fresh(['plan']));

            return $updated;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transitions (called by CheckSubscriptionStatuses command)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transition a subscription to past_due and start the grace window.
     */
    public function markPastDue(Subscription $subscription): Subscription
    {
        $graceEndsAt = Carbon::now()->addDays(self::GRACE_PERIOD_DAYS);

        $updated = $this->subscriptionRepo->update($subscription, [
            'status'               => SubscriptionStatus::PAST_DUE->value,
            'grace_period_ends_at' => $graceEndsAt,
        ]);

        Log::info('[Billing] Subscription marked past_due', [
            'subscription_id'      => $updated->id,
            'grace_period_ends_at' => $graceEndsAt->toDateTimeString(),
        ]);

        return $updated;
    }

    /**
     * Process all overdue status transitions.
     * Intended to be called by CheckSubscriptionStatuses artisan command.
     */
    public function handleGracePeriod(): void
    {
        // 1. Mark active/trial subscriptions past their billing date as past_due
        $this->subscriptionRepo->getSubscriptionsNeedingGrace()
            ->each(fn(Subscription $sub) => $this->markPastDue($sub));

        // 2. Suspend past_due subscriptions whose grace window has closed
        $this->subscriptionRepo->getSubscriptionsGraceExpired()
            ->each(fn(Subscription $sub) => $this->suspendSubscription($sub));

        // 3. Handle expired trials that never had a payment approved
        $this->subscriptionRepo->getTrialSubscriptionsExpired()
            ->each(fn(Subscription $sub) => $this->markPastDue($sub));
    }

    /**
     * Suspend a facility's subscription, blocking all API access.
     */
    public function suspendSubscription(Subscription $subscription): Subscription
    {
        $updated = $this->subscriptionRepo->update($subscription, [
            'status'       => SubscriptionStatus::SUSPENDED->value,
            'suspended_at' => Carbon::now(),
        ]);

        Log::warning('[Billing] Subscription suspended', [
            'subscription_id' => $updated->id,
            'facility_id'     => $updated->facility_id,
        ]);

        return $updated;
    }

    /**
     * Cancel a subscription (voluntary or admin-initiated).
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): Subscription
    {
        $updated = $this->subscriptionRepo->update($subscription, [
            'status'       => SubscriptionStatus::CANCELLED->value,
            'cancelled_at' => Carbon::now(),
            'notes'        => $reason
                ? ($subscription->notes ? $subscription->notes . "\nCancelled: $reason" : "Cancelled: $reason")
                : $subscription->notes,
        ]);

        Log::info('[Billing] Subscription cancelled', [
            'subscription_id' => $updated->id,
            'reason'          => $reason,
        ]);

        return $updated;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────────────────────────────────

    public function getActiveSubscription(int $facilityId): ?Subscription
    {
        return $this->subscriptionRepo->findActiveByFacility($facilityId);
    }

    public function getSubscriptionForFacility(int $facilityId): ?Subscription
    {
        return $this->subscriptionRepo->findByFacility($facilityId);
    }

    public function getAllSubscriptions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->subscriptionRepo->getAllPaginated($filters, $perPage);
    }
}
