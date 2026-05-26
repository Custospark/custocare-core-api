<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\SubscriptionScheduledChangeStatus;
use App\Enums\Billing\SubscriptionScheduledChangeType;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionScheduledChange;
use App\Models\User;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Billing\Contracts\SubscriptionScheduledChangeRepositoryInterface;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionScheduledChangeService implements SubscriptionScheduledChangeServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
        private readonly SubscriptionScheduledChangeRepositoryInterface $scheduledChangeRepo,
        private readonly FacilityStaffRoleModuleSyncServiceInterface $moduleSyncService,
        private readonly NotificationService $notificationService,
    ) {}

    public function applyPendingScheduledChanges(Subscription $subscription): Subscription
    {
        $due = $this->scheduledChangeRepo->findDuePendingForSubscription($subscription->id);

        if (! $due) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription, $due) {
            $fresh = $this->subscriptionRepo->findById($subscription->id) ?? $subscription;

            return match ($due->change_type) {
                SubscriptionScheduledChangeType::CANCEL => $this->applyScheduledCancel($fresh, $due),
                SubscriptionScheduledChangeType::UPGRADE,
                SubscriptionScheduledChangeType::DOWNGRADE,
                SubscriptionScheduledChangeType::PLAN_CHANGE => $this->applyScheduledPlanChange($fresh, $due),
            };
        });
    }

    public function schedulePlanChange(
        Subscription $subscription,
        Plan $targetPlan,
        string $changeType,
        ?User $requestedBy = null,
    ): SubscriptionScheduledChange {
        if (! $subscription->hasAccess()) {
            throw new \DomainException('An active paid subscription is required to schedule a plan change.', 422);
        }

        if ($subscription->plan_id === $targetPlan->id) {
            throw new \DomainException('You are already on this plan.', 422);
        }

        $this->scheduledChangeRepo->cancelPendingForSubscription($subscription->id);

        $type = SubscriptionScheduledChangeType::from($changeType);
        $effectiveAt = $subscription->next_billing_date ?? $subscription->ends_at ?? Carbon::now()->addMonth();

        $change = $this->scheduledChangeRepo->create([
            'subscription_id'       => $subscription->id,
            'facility_id'           => $subscription->facility_id,
            'change_type'           => $type->value,
            'from_plan_id'          => $subscription->plan_id,
            'to_plan_id'            => $targetPlan->id,
            'effective_at'          => $effectiveAt,
            'status'                => SubscriptionScheduledChangeStatus::PENDING->value,
            'requested_by_user_id'  => $requestedBy?->id,
            'metadata'              => [
                'target_plan_name' => $targetPlan->name,
            ],
        ]);

        // Send scheduled change confirmation
        try {
            $facility = $subscription->facility;
            if ($facility) {
                $changeLabel = $type === SubscriptionScheduledChangeType::UPGRADE ? 'upgrade' : 'plan change';
                $this->notificationService->sendBillingToFacility(
                    $facility,
                    "Your {$changeLabel} to {$targetPlan->name} has been scheduled",
                    "<p>A {$changeLabel} to <strong>{$targetPlan->name}</strong> has been scheduled for your subscription.</p>
                    <p>This change will take effect on <strong>{$effectiveAt->format('F j, Y')}</strong>.</p>",
                );
            }
        } catch (\Exception $e) {
            Log::error('[Billing] Failed to send scheduled change email', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return $change;
    }

    public function scheduleCancellation(
        Subscription $subscription,
        ?User $requestedBy = null,
    ): SubscriptionScheduledChange {
        $this->scheduledChangeRepo->cancelPendingForSubscription($subscription->id);

        $effectiveAt = $subscription->next_billing_date ?? $subscription->ends_at ?? Carbon::now()->addMonth();

        return $this->scheduledChangeRepo->create([
            'subscription_id'      => $subscription->id,
            'facility_id'          => $subscription->facility_id,
            'change_type'          => SubscriptionScheduledChangeType::CANCEL->value,
            'from_plan_id'         => $subscription->plan_id,
            'to_plan_id'           => null,
            'effective_at'         => $effectiveAt,
            'status'               => SubscriptionScheduledChangeStatus::PENDING->value,
            'requested_by_user_id' => $requestedBy?->id,
            'metadata'             => [],
        ]);
    }

    public function cancelPendingChange(Subscription $subscription): void
    {
        $this->scheduledChangeRepo->cancelPendingForSubscription($subscription->id);

        $metadata = $subscription->metadata ?? [];
        unset($metadata['cancel_at_period_end'], $metadata['access_ends_at']);
        $this->subscriptionRepo->update($subscription, ['metadata' => $metadata]);
    }

    public function getPendingChange(Subscription $subscription): ?SubscriptionScheduledChange
    {
        return $this->scheduledChangeRepo->findPendingForSubscription($subscription->id);
    }

    private function applyScheduledPlanChange(Subscription $subscription, SubscriptionScheduledChange $change): Subscription
    {
        $updated = $this->subscriptionRepo->update($subscription, [
            'plan_id'  => $change->to_plan_id,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'cancel_at_period_end' => false,
            ]),
        ]);

        $this->scheduledChangeRepo->update($change, [
            'status' => SubscriptionScheduledChangeStatus::APPLIED->value,
        ]);

        $this->moduleSyncService->syncForSubscription($updated->fresh(['plan']));

        Log::info('[Billing] Scheduled plan change applied', [
            'subscription_id' => $updated->id,
            'to_plan_id'        => $change->to_plan_id,
        ]);

        // Send plan change applied notification
        try {
            $facility = $updated->facility;
            $targetPlan = Plan::find($change->to_plan_id);
            if ($facility && $targetPlan) {
                $this->notificationService->sendBillingToFacility(
                    $facility,
                    "Your plan has been changed to {$targetPlan->name}",
                    "<p>Your scheduled plan change has been applied. Your subscription is now on <strong>{$targetPlan->name}</strong>.</p>
                    <p>Your new plan features and limits are now active.</p>",
                );
            }
        } catch (\Exception $e) {
            Log::error('[Billing] Failed to send scheduled change applied email', [
                'subscription_id' => $updated->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return $updated->fresh(['plan']);
    }

    private function applyScheduledCancel(Subscription $subscription, SubscriptionScheduledChange $change): Subscription
    {
        $updated = $this->subscriptionRepo->update($subscription, [
            'status'       => SubscriptionStatus::CANCELLED->value,
            'cancelled_at' => Carbon::now(),
            'metadata'     => array_merge($subscription->metadata ?? [], [
                'cancel_at_period_end' => false,
                'access_ends_at'       => null,
            ]),
        ]);

        $this->scheduledChangeRepo->update($change, [
            'status' => SubscriptionScheduledChangeStatus::APPLIED->value,
        ]);

        Log::info('[Billing] Scheduled cancellation applied', [
            'subscription_id' => $updated->id,
        ]);

        return $updated->fresh(['plan']);
    }
}
