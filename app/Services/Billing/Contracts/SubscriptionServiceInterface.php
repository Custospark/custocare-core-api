<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Facility;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionServiceInterface
{
    public function createSubscription(Facility $facility, Plan $plan, array $options = []): Subscription;
    public function activateSubscription(Subscription $subscription, Payment $payment, ?User $approvedBy): Subscription;
    public function renewSubscription(Subscription $subscription, Payment $payment, ?User $approvedBy): Subscription;
    public function markPastDue(Subscription $subscription): Subscription;
    public function handleGracePeriod(): void;
    public function suspendSubscription(Subscription $subscription): Subscription;
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): Subscription;
    public function getActiveSubscription(int $facilityId): ?Subscription;
    public function getSubscriptionForFacility(int $facilityId): ?Subscription;
    public function getAllSubscriptions(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
