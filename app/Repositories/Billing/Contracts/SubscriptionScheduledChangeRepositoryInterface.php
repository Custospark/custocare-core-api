<?php

declare(strict_types=1);

namespace App\Repositories\Billing\Contracts;

use App\Models\SubscriptionScheduledChange;

interface SubscriptionScheduledChangeRepositoryInterface
{
    public function findPendingForSubscription(int $subscriptionId): ?SubscriptionScheduledChange;

    public function findDuePendingForSubscription(int $subscriptionId): ?SubscriptionScheduledChange;

    public function create(array $data): SubscriptionScheduledChange;

    public function update(SubscriptionScheduledChange $change, array $data): SubscriptionScheduledChange;

    public function cancelPendingForSubscription(int $subscriptionId): void;
}
