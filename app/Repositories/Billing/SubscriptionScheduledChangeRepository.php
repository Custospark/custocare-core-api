<?php

declare(strict_types=1);

namespace App\Repositories\Billing;

use App\Enums\Billing\SubscriptionScheduledChangeStatus;
use App\Models\SubscriptionScheduledChange;
use App\Repositories\Billing\Contracts\SubscriptionScheduledChangeRepositoryInterface;

class SubscriptionScheduledChangeRepository implements SubscriptionScheduledChangeRepositoryInterface
{
    public function findPendingForSubscription(int $subscriptionId): ?SubscriptionScheduledChange
    {
        return SubscriptionScheduledChange::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionScheduledChangeStatus::PENDING->value)
            ->with(['fromPlan', 'toPlan'])
            ->first();
    }

    public function findDuePendingForSubscription(int $subscriptionId): ?SubscriptionScheduledChange
    {
        return SubscriptionScheduledChange::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionScheduledChangeStatus::PENDING->value)
            ->where('effective_at', '<=', now())
            ->with(['fromPlan', 'toPlan'])
            ->first();
    }

    public function create(array $data): SubscriptionScheduledChange
    {
        return SubscriptionScheduledChange::create($data);
    }

    public function update(SubscriptionScheduledChange $change, array $data): SubscriptionScheduledChange
    {
        $change->update($data);

        return $change->fresh(['fromPlan', 'toPlan']);
    }

    public function cancelPendingForSubscription(int $subscriptionId): void
    {
        SubscriptionScheduledChange::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', SubscriptionScheduledChangeStatus::PENDING->value)
            ->update(['status' => SubscriptionScheduledChangeStatus::CANCELLED->value]);
    }
}
