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
use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Billing\BillingFacilitySummaryService;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Enums\Billing\SubscriptionScheduledChangeType;
use App\Services\Notification\NotificationService;
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
        private readonly SubscriptionScheduledChangeServiceInterface $scheduledChangeService,
        private readonly BillingFacilitySummaryService $billingFacilitySummaryService,
        private readonly SubscriptionBillingPdfServiceInterface $billingPdfService,
        private readonly NotificationService $notificationService,
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

            Log::debug('[Billing] createSubscription — existing lookup', [
                'facility_id' => $facility->id,
                'existing_id' => $existing?->id,
                'existing_status' => $existing?->status?->value,
                'existing_has_access' => $existing?->hasAccess(),
                'existing_trial_ends_at' => $existing?->trial_ends_at?->toISOString(),
            ]);

            if ($existing) {
                // Active subscription that still grants access — update plan, don't create new
                if ($existing->hasAccess()) {
                    $updated = $this->subscriptionRepo->update($existing, array_filter([
                        'plan_id'       => $plan->id,
                        'billing_cycle' => $options['billing_cycle'] ?? null,
                    ], fn($v) => $v !== null));
                    $updated->_action = 'updated';
                    Log::info('[Billing] Subscription plan updated (switch within active/trial)', [
                        'subscription_id' => $existing->id,
                        'new_plan'        => $plan->name,
                        'billing_cycle'   => $options['billing_cycle'] ?? $existing->billing_cycle,
                    ]);
                    return $updated;
                }
                // Subscription exists but has no access (trial expired, past_due expired, etc.)
                // Update it with fresh details below
            }

            $now = Carbon::now();

            // ── Calculate remaining trial days to prevent abuse ──
            $remainingTrialDays = $this->subscriptionRepo->getRemainingTrialDays($facility->id, $plan->trial_days);

            $status = $remainingTrialDays > 0
                ? SubscriptionStatus::TRIAL->value
                : SubscriptionStatus::PAST_DUE->value;

            $trialEndsAt = $remainingTrialDays > 0 ? $now->copy()->addDays($remainingTrialDays) : null;

            // Only grant grace period once per facility — prevent infinite grace abuse
            $hasUsedGraceBefore = $existing && $existing->grace_period_ends_at !== null;
            Log::debug('[Billing] createSubscription — grace check', [
                'has_existing'         => $existing ? 'yes' : 'no',
                'existing_grace'       => $existing?->grace_period_ends_at?->toDateString(),
                'has_used_grace_before' => $hasUsedGraceBefore ? 'yes' : 'no',
                'will_grant_grace'     => ($remainingTrialDays <= 0 && !$hasUsedGraceBefore) ? 'yes' : 'no',
            ]);
            $graceEndsAt = $remainingTrialDays <= 0 && !$hasUsedGraceBefore
                ? $now->copy()->addDays(self::GRACE_PERIOD_DAYS)
                : null;

            $selectedCycle = $options['billing_cycle'] ?? $plan->billing_cycle ?? 'monthly';
            $monthsToAdd = BillingCycle::tryFrom($selectedCycle)?->monthsToAdd() ?? 1;

            // Billing period starts after trial ends so users enjoy full trial days
            $billingStartsFrom = $trialEndsAt && $trialEndsAt->isFuture()
                ? $trialEndsAt->copy()
                : $now->copy();

            // For trial subscriptions, next_billing_date = trial end (first payment due)
            // For non-trial (past_due), next_billing_date = end of current period
            $billingPeriodEnd = $billingStartsFrom->copy()->addMonths($monthsToAdd);

            // ── Build the subscription payload ────────────────────────────
            $payload = [
                'plan_id'            => $plan->id,
                'billing_cycle'      => $selectedCycle,
                'status'             => $status,
                'starts_at'          => $now,
                'ends_at'            => $billingPeriodEnd,
                'next_billing_date'  => $status === SubscriptionStatus::TRIAL->value
                    ? $billingStartsFrom  // trial end = first payment due
                    : $billingPeriodEnd,  // past_due = end of billing period
                'onboarding_fee_paid' => false,
                'notes'              => $options['notes'] ?? null,
                'metadata'           => $options['metadata'] ?? null,
            ];

            // Only set trial_ends_at and grace_period_ends_at when granting — preserve historical markers
            if ($trialEndsAt !== null) {
                $payload['trial_ends_at'] = $trialEndsAt;
            }
            if ($graceEndsAt !== null) {
                $payload['grace_period_ends_at'] = $graceEndsAt;
            }

            if ($existing) {
                // Update existing subscription with new details
                $subscription = $this->subscriptionRepo->update($existing, $payload);
                $subscription->_action = 'updated';
                Log::info('[Billing] Subscription updated (resubscribed)', [
                    'subscription_id' => $subscription->id,
                    'facility_id'     => $facility->id,
                    'new_plan'        => $plan->name,
                    'status'          => $status,
                    '_action'         => 'updated',
                ]);
            } else {
                // First-time subscription — create with facility_id
                $payload['facility_id'] = $facility->id;
                $subscription = $this->subscriptionRepo->create($payload);
                $subscription->_action = 'created';
                Log::info('[Billing] Subscription created', [
                    'subscription_id' => $subscription->id,
                    'facility_id'     => $facility->id,
                    'plan'            => $plan->name,
                    'status'          => $status,
                    '_action'         => 'created',
                ]);
            }

            $subscription = $subscription->fresh(['plan']);

            if ($subscription->hasAccess()) {
                $this->moduleSyncService->syncForSubscription($subscription);
            }

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
            $remainingTrialDays = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
                ? max(0, $now->diffInDays($subscription->trial_ends_at, false))
                : 0;
            $monthsToAdd = BillingCycle::tryFrom($subscription->billing_cycle ?? 'monthly')?->monthsToAdd() ?? 1;
            $periodEnd = $now->copy()->addMonths($monthsToAdd)->addDays($remainingTrialDays);

            $updated = $this->subscriptionRepo->update($subscription, [
                'status'              => SubscriptionStatus::ACTIVE->value,
                'starts_at'           => $now->copy(),
                'ends_at'             => $periodEnd->copy(),
                'next_billing_date'   => $periodEnd->copy(),
                'suspended_at'        => null,
                'approved_at'         => $now->copy(),
                'approved_by_user_id' => $approvedBy ? $approvedBy->id : null,
                'onboarding_fee_paid' => $payment->payment_type === PaymentType::ONBOARDING
                    ? true
                    : $subscription->onboarding_fee_paid,
                'metadata'            => $this->metadataWithLockedPeriodPrice($subscription, $payment),
            ]);

            Log::info('[Billing] Subscription activated', [
                'subscription_id' => $updated->id,
                'facility_id'     => $updated->facility_id,
                'approved_by'     => $approvedBy->id,
            ]);

            $this->moduleSyncService->syncForSubscription($updated->fresh(['plan']));

            // Send invoice to facility owner(s)
            try {
                $facility = $updated->facility;
                $payment->loadMissing('invoice');
                $invoice = $payment->invoice;
                if ($facility && $invoice) {
                    $pdfContent = $this->billingPdfService->generateInvoicePdfContent($invoice);

                    $this->notificationService->sendBillingToFacility(
                        $facility,
                        "Your {$updated->plan?->name} subscription is now active",
                        "<p>Your <strong>{$updated->plan?->name}</strong> subscription for <strong>{$facility->facility_name}</strong> is now active.</p>
                        " . \App\Services\Notification\NotificationService::billingInfoBlock($updated) . "
                        <p>Your invoice <strong>{$invoice->invoice_number}</strong> is attached below. Thank you for choosing Custocare.</p>",
                        [
                            [
                                'data' => $pdfContent,
                                'name' => str_replace('/', '_', $invoice->invoice_number) . '.pdf',
                                'mime' => 'application/pdf',
                            ],
                        ],
                    );
                }
            } catch (\Exception $e) {
                Log::error('[Billing] Failed to send activation invoice email', [
                    'subscription_id' => $updated->id,
                    'error'           => $e->getMessage(),
                ]);
            }

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

            // Calculate from now if the subscription was just created (resubscribe)
            // Otherwise extend from current period end to not penalise early renewal
            $justCreated = $subscription->starts_at && $subscription->starts_at->diffInMinutes(now()) < 60;
            $monthsToAdd = BillingCycle::tryFrom($subscription->billing_cycle ?? 'monthly')?->monthsToAdd() ?? 1;
            $periodEnd = $subscription->currentPeriodEndsAt();
            $newEndsAt = $justCreated || !$periodEnd
                ? Carbon::now()->copy()->addMonths($monthsToAdd)
                : $periodEnd->copy()->addMonths($monthsToAdd);

            $plan = $subscription->plan ?? Plan::find($subscription->plan_id);

            $updated = $this->subscriptionRepo->update($subscription, [
                'status'               => SubscriptionStatus::ACTIVE->value,
                'ends_at'              => $newEndsAt,
                'next_billing_date'    => $newEndsAt->copy(),
                'suspended_at'         => null,
                'metadata'             => $this->metadataWithLockedPeriodPrice(
                    $subscription,
                    $payment,
                    $plan ? (float) $plan->price_usd : null,
                ),
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
        // Only grant grace if facility hasn't used one before
        $graceEndsAt = $subscription->grace_period_ends_at === null
            ? Carbon::now()->addDays(self::GRACE_PERIOD_DAYS)
            : null;

        $updated = $this->subscriptionRepo->update($subscription, array_filter([
            'status'               => SubscriptionStatus::PAST_DUE->value,
            'grace_period_ends_at' => $graceEndsAt,
        ], fn($v) => $v !== null));

        Log::info('[Billing] Subscription marked past_due', [
            'subscription_id'      => $updated->id,
            'grace_period_ends_at' => $graceEndsAt?->toDateTimeString(),
            'grace_granted'        => $graceEndsAt !== null,
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
     * Cancel a subscription.
     *
     * @param  string  $mode  `at_period_end` (default) or `immediate`
     */
    public function cancelSubscription(
        Subscription $subscription,
        ?string $reason = null,
        string $mode = 'at_period_end',
    ): Subscription {
        if ($mode === 'immediate') {
            return $this->cancelSubscriptionImmediately($subscription, $reason);
        }

        return $this->cancelSubscriptionAtPeriodEnd($subscription, $reason);
    }

    public function cancelSubscriptionImmediately(Subscription $subscription, ?string $reason = null): Subscription
    {
        $this->scheduledChangeService->cancelPendingChange($subscription);

        $updated = $this->subscriptionRepo->update($subscription, [
            'status'       => SubscriptionStatus::CANCELLED->value,
            'cancelled_at' => Carbon::now(),
            'metadata'     => array_merge($subscription->metadata ?? [], [
                'cancel_at_period_end' => false,
                'access_ends_at'       => null,
            ]),
            'notes'        => $this->appendNote($subscription, $reason, 'Cancelled immediately'),
        ]);

        Log::info('[Billing] Subscription cancelled immediately', [
            'subscription_id' => $updated->id,
            'reason'          => $reason,
        ]);

        return $updated;
    }

    public function cancelSubscriptionAtPeriodEnd(Subscription $subscription, ?string $reason = null): Subscription
    {
        if (! $subscription->hasAccess()) {
            return $this->cancelSubscriptionImmediately($subscription, $reason);
        }

        $this->scheduledChangeService->cancelPendingChange($subscription);

        $monthsToAdd = BillingCycle::tryFrom($subscription->billing_cycle ?? 'monthly')?->monthsToAdd() ?? 1;
        $effectiveAt = $subscription->ends_at ?? $subscription->next_billing_date ?? Carbon::now()->addMonths($monthsToAdd);

        $this->scheduledChangeService->scheduleCancellation($subscription);

        $updated = $this->subscriptionRepo->update($subscription, [
            'metadata' => array_merge($subscription->metadata ?? [], [
                'cancel_at_period_end' => true,
                'access_ends_at'       => $effectiveAt->toISOString(),
            ]),
            'notes'    => $this->appendNote($subscription, $reason, 'Cancellation scheduled at period end'),
        ]);

        Log::info('[Billing] Subscription cancellation scheduled at period end', [
            'subscription_id' => $updated->id,
            'effective_at'      => $effectiveAt->toDateTimeString(),
        ]);

        return $updated->fresh(['plan']);
    }

    /**
     * Apply plan upgrade immediately (after upgrade-now payment is approved).
     */
    public function upgradeNow(Subscription $subscription, Plan $plan, ?User $approvedBy = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $approvedBy) {
            $this->scheduledChangeService->cancelPendingChange($subscription);

            $updated = $this->subscriptionRepo->update($subscription, [
                'plan_id'  => $plan->id,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancel_at_period_end' => false,
                    'access_ends_at'       => null,
                    'pending_upgrade_plan_id' => null,
                    'billing_period_price_usd' => $subscription->billing_cycle === 'yearly'
                        ? round((float) $plan->price_usd * 10, 2)
                        : round((float) $plan->price_usd, 2),
                    'billing_period_price_locked_at' => Carbon::now()->toISOString(),
                ]),
                'notes'    => $this->appendNote(
                    $subscription,
                    null,
                    "Upgraded to {$plan->name}" . ($approvedBy ? " (approved by user #{$approvedBy->id})" : ''),
                ),
            ]);

            $this->moduleSyncService->syncForSubscription($updated->fresh(['plan']));

            Log::info('[Billing] Subscription upgraded immediately', [
                'subscription_id' => $updated->id,
                'plan_id'           => $plan->id,
            ]);

            // Send upgrade notification
            try {
                $facility = $updated->facility;
                if ($facility) {
                    $this->notificationService->sendBillingToFacility(
                        $facility,
                        "Your plan has been upgraded to {$plan->name}",
                        "<p>Your subscription for <strong>{$facility->facility_name}</strong> has been upgraded to <strong>{$plan->name}</strong>.</p>
                        " . \App\Services\Notification\NotificationService::billingInfoBlock($updated) . "
                        <p>Your new plan features and limits are now active. Thank you for growing with Custocare.</p>",
                    );
                }
            } catch (\Exception $e) {
                Log::error('[Billing] Failed to send upgrade notification email', [
                    'subscription_id' => $updated->id,
                    'error'           => $e->getMessage(),
                ]);
            }

            return $updated->fresh(['plan']);
        });
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
        $subscription = $this->subscriptionRepo->findByFacility($facilityId);

        if (! $subscription) {
            return null;
        }

        // Auto-transition based on dates (no cron job needed)
        $now = Carbon::now();

        if ($subscription->status === SubscriptionStatus::ACTIVE || $subscription->status === SubscriptionStatus::TRIAL) {
            $isDue = ($subscription->next_billing_date && $subscription->next_billing_date->isPast())
                || ($subscription->status === SubscriptionStatus::TRIAL && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast());
            if ($isDue) {
                $subscription = $this->markPastDue($subscription);
            }
        }

        if ($subscription->status === SubscriptionStatus::PAST_DUE) {
            if ($subscription->grace_period_ends_at && $subscription->grace_period_ends_at->isPast()) {
                $subscription = $this->suspendSubscription($subscription);
            }
        }

        // Apply due pending scheduled changes (effective_at is past)
        $subscription = $this->scheduledChangeService->applyPendingScheduledChanges($subscription);

        return $subscription;
    }

    private function appendNote(Subscription $subscription, ?string $reason, string $prefix): ?string
    {
        $line = $reason ? "{$prefix}: {$reason}" : $prefix;

        return $subscription->notes ? $subscription->notes . "\n{$line}" : $line;
    }

    /**
     * Lock the monthly rate for the current billing period (used in proration & display).
     *
     * @param  float|null  $overrideUsd  Explicit catalog price (e.g. after renewal).
     */
    private function metadataWithLockedPeriodPrice(
        Subscription $subscription,
        Payment $payment,
        ?float $overrideUsd = null,
    ): array {
        $price = $overrideUsd ?? $this->resolveMonthlyPriceFromPayment($subscription, $payment);

        return array_merge($subscription->metadata ?? [], [
            'billing_period_price_usd'       => round($price, 2),
            'billing_period_price_locked_at' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Prefer the quoted line item for the subscription's billing cycle; fall back to plan catalog price.
     */
    private function resolveMonthlyPriceFromPayment(Subscription $subscription, Payment $payment): float
    {
        $quote = $subscription->metadata['latest_quote'] ?? null;
        $cycle = $subscription->billing_cycle ?? 'monthly';

        if (is_array($quote) && ! empty($quote['line_items'])) {
            foreach ($quote['line_items'] as $item) {
                $label = strtolower((string) ($item['label'] ?? ''));
                if (str_contains($label, $cycle) && isset($item['amount'])) {
                    return (float) $item['amount'];
                }
            }
        }

        $plan = $subscription->plan ?? Plan::find($subscription->plan_id);

        if ($plan) {
            return $cycle === 'yearly'
                ? round((float) $plan->price_usd * 10, 2)
                : (float) $plan->price_usd;
        }

        return (float) $payment->amount;
    }

    public function getAllSubscriptions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->subscriptionRepo->getAllPaginated($filters, $perPage);

        return $this->billingFacilitySummaryService->enrichSubscriptionPaginator($paginator);
    }
}
