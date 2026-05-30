<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;

class SubscriptionPaymentQuoteService implements SubscriptionPaymentQuoteServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
    ) {}

    public function buildQuote(Subscription $subscription, ?Plan $targetPlan, string $intent, ?string $billingCycleOverride = null): array
    {
        $plan = $subscription->plan ?? Plan::find($subscription->plan_id);
        $lineItems = [];
        $total = 0.0;
        $paymentType = 'subscription';
        $notes = null;

        match ($intent) {
            'first_activation', 'subscription' => $this->buildActivationQuote($subscription, $plan, $lineItems, $total, $paymentType, $billingCycleOverride),
            'renewal' => $this->buildRenewalQuote($subscription, $plan, $lineItems, $total, $paymentType, $billingCycleOverride),
            'scheduled_change' => $this->buildScheduledChangeQuote($targetPlan, $lineItems, $total),
            'upgrade_now' => $this->buildUpgradeNowQuote($subscription, $plan, $targetPlan, $lineItems, $total, $paymentType, $billingCycleOverride),
            default => throw new \InvalidArgumentException("Unknown payment quote intent: {$intent}"),
        };

        $quote = [
            'intent'                   => $intent,
            'line_items'               => $lineItems,
            'total_usd'                => round($total, 2),
            'currency'                 => 'USD',
            'payment_type'             => $paymentType,
            'onboarding_fee_applicable'  => ! $subscription->onboarding_fee_paid && $plan->hasOnboardingFee(),
            'trial_days'               => (int) $plan->trial_days,
            'target_plan_id'           => $targetPlan?->id,
            'effective_at'             => $subscription->next_billing_date?->toISOString(),
            'notes'                    => $notes,
        ];

        $metadata = array_merge($subscription->metadata ?? [], [
            'latest_quote' => array_merge($quote, [
                'expires_at' => now()->addHours(24)->toISOString(),
            ]),
        ]);
        $this->subscriptionRepo->update($subscription, ['metadata' => $metadata]);

        return $quote;
    }

    public function validatePaymentAmount(
        Subscription $subscription,
        float $amount,
        string $intent,
        ?int $targetPlanId = null,
    ): void {
        $targetPlan = $targetPlanId ? Plan::findOrFail($targetPlanId) : null;
        $quote = $this->buildQuote($subscription, $targetPlan, $intent);

        if (abs($amount - (float) $quote['total_usd']) > 0.01) {
            throw new \DomainException(
                sprintf(
                    'Payment amount $%.2f does not match the quoted total $%.2f.',
                    $amount,
                    $quote['total_usd']
                ),
                422
            );
        }
    }

    private function resolveBillingCycle(Subscription $subscription, Plan $plan, ?string $override = null): BillingCycle
    {
        return BillingCycle::tryFrom($override ?? $subscription->billing_cycle ?? $plan->billing_cycle ?? 'monthly')
            ?? BillingCycle::MONTHLY;
    }

    private function buildActivationQuote(
        Subscription $subscription,
        Plan $plan,
        array &$lineItems,
        float &$total,
        string &$paymentType,
        ?string $billingCycleOverride = null,
    ): void {
        $cycle = $this->resolveBillingCycle($subscription, $plan, $billingCycleOverride);
        $months = $cycle->monthsToAdd();
        $isAnnual = $cycle === BillingCycle::YEARLY;
        $price = (float) $plan->price_usd;
        $label = "{$plan->name} ({$cycle->value})";

        if ($isAnnual) {
            $price = round($price * 10, 2);
        }

        $lineItems[] = ['label' => $label, 'amount' => $price];
        $total += $price;

        if (! $subscription->onboarding_fee_paid && $plan->hasOnboardingFee()) {
            $fee = (float) $plan->onboarding_fee_usd;
            $lineItems[] = ['label' => 'Onboarding fee', 'amount' => $fee];
            $total += $fee;
            $paymentType = 'onboarding';
        }

        $paymentType = $subscription->status === SubscriptionStatus::TRIAL ? 'subscription' : $paymentType;
    }

    private function buildRenewalQuote(Subscription $subscription, Plan $plan, array &$lineItems, float &$total, string &$paymentType, ?string $billingCycleOverride = null): void
    {
        $cycle = $this->resolveBillingCycle($subscription, $plan, $billingCycleOverride);
        $months = $cycle->monthsToAdd();
        $isAnnual = $cycle === BillingCycle::YEARLY;
        $price = (float) $plan->price_usd;
        $label = "{$plan->name} renewal ({$cycle->value})";

        if ($isAnnual) {
            $price = round($price * 10, 2);
        }

        $lineItems[] = ['label' => $label, 'amount' => $price];
        $total = $price;
        $paymentType = 'renewal';
    }

    private function buildScheduledChangeQuote(?Plan $targetPlan, array &$lineItems, float &$total): void
    {
        if (! $targetPlan) {
            throw new \InvalidArgumentException('target plan is required for scheduled_change quote.');
        }

        $lineItems[] = [
            'label'  => "Scheduled switch to {$targetPlan->name}",
            'amount' => 0,
        ];
        $total = 0;
    }

    private function buildUpgradeNowQuote(
        Subscription $subscription,
        Plan $currentPlan,
        ?Plan $targetPlan,
        array &$lineItems,
        float &$total,
        string &$paymentType,
        ?string $billingCycleOverride = null,
    ): void {
        if (! $targetPlan) {
            throw new \InvalidArgumentException('target plan is required for upgrade_now quote.');
        }

        if ($targetPlan->price_usd <= $currentPlan->price_usd) {
            throw new \DomainException('Upgrade now requires a higher-priced plan.', 422);
        }

        // Proration: credit unused time at the locked period rate; charge remainder at target catalog price.
        $breakdown = SubscriptionProrationCalculator::calculate($subscription, $currentPlan, $targetPlan, $billingCycleOverride);

        $lineItems[] = [
            'label'  => "Proration: {$currentPlan->name} → {$targetPlan->name}",
            'amount' => $breakdown['proration_due'],
        ];
        $lineItems[] = [
            'label'  => "({$breakdown['days_remaining']} days remaining in billing period)",
            'amount' => 0,
        ];

        $total = $breakdown['proration_due'];
        $paymentType = 'upgrade_proration';
    }
}
