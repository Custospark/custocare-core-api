<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Derives facility-facing payment guidance from subscription + payment records.
 * Single source of truth for "Complete payment" / pending approval UI states.
 */
class SubscriptionPaymentActionResolver
{
    public function resolve(Subscription $subscription): array
    {
        $hasPending = $subscription->relationLoaded('payments')
            ? $subscription->payments->contains(
                fn ($payment) => $payment->status === PaymentStatus::PENDING,
            )
            : $subscription->payments()
                ->where('status', PaymentStatus::PENDING->value)
                ->exists();

        if ($hasPending) {
            return [
                'required'           => false,
                'pending_approval'   => true,
                'plan_id'            => $subscription->plan_id,
                'intent'             => null,
                'label'              => null,
                'message'            => 'Your payment proof is pending platform admin approval.',
            ];
        }

        $metadata = $subscription->metadata ?? [];
        $pendingUpgradePlanId = isset($metadata['pending_upgrade_plan_id'])
            ? (int) $metadata['pending_upgrade_plan_id']
            : null;

        if ($pendingUpgradePlanId) {
            $targetPlan = Plan::find($pendingUpgradePlanId);

            return [
                'required'           => true,
                'pending_approval'   => false,
                'plan_id'            => $pendingUpgradePlanId,
                'intent'             => 'upgrade_now',
                'label'              => 'Complete payment',
                'message'            => $targetPlan
                    ? "Complete payment to upgrade to {$targetPlan->name}."
                    : 'Complete payment to finish your plan upgrade.',
            ];
        }

        if (
            $subscription->status === SubscriptionStatus::TRIAL
            && ! $subscription->approved_at
        ) {
            return [
                'required'           => true,
                'pending_approval'   => false,
                'plan_id'            => $subscription->plan_id,
                'intent'             => 'subscription',
                'label'              => 'Complete payment',
                'message'            => 'Complete payment to activate your subscription.',
            ];
        }

        if ($subscription->status === SubscriptionStatus::PAST_DUE) {
            return [
                'required'           => true,
                'pending_approval'   => false,
                'plan_id'            => $subscription->plan_id,
                'intent'             => 'renewal',
                'label'              => 'Complete payment',
                'message'            => 'Your subscription payment is overdue. Complete payment to restore full access.',
            ];
        }

        return [
            'required'           => false,
            'pending_approval'   => false,
            'plan_id'            => null,
            'intent'             => null,
            'label'              => null,
            'message'            => null,
        ];
    }
}
