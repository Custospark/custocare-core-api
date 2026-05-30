<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionScheduledChange;
use App\Models\User;

interface SubscriptionScheduledChangeServiceInterface
{
    public function applyPendingScheduledChanges(Subscription $subscription): Subscription;

    /** Apply ALL pending scheduled changes regardless of effective_at date. */
    public function applyAllPendingChanges(Subscription $subscription): Subscription;

    public function schedulePlanChange(
        Subscription $subscription,
        Plan $targetPlan,
        string $changeType,
        ?User $requestedBy = null,
    ): SubscriptionScheduledChange;

    public function scheduleCancellation(
        Subscription $subscription,
        ?User $requestedBy = null,
    ): SubscriptionScheduledChange;

    public function cancelPendingChange(Subscription $subscription): void;

    public function getPendingChange(Subscription $subscription): ?SubscriptionScheduledChange;
}
