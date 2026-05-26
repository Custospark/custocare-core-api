<?php

declare(strict_types=1);

namespace App\Repositories\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findById(int $id): ?Subscription
    {
        return Subscription::with(['facility', 'plan', 'payments'])->find($id);
    }

    /** Latest subscription for a facility regardless of status. */
    public function findByFacility(int $facilityId): ?Subscription
    {
        return Subscription::forFacility($facilityId)
            ->with(['plan', 'payments'])
            ->latest()
            ->first();
    }

    /** Strictly active subscription for a facility. */
    public function findActiveByFacility(int $facilityId): ?Subscription
    {
        return Subscription::forFacility($facilityId)
            ->active()
            ->with(['plan'])
            ->first();
    }

    /**
     * Returns the subscription if the facility currently has system access:
     * active, valid trial, or within grace period.
     */
    public function findAccessibleByFacility(int $facilityId): ?Subscription
    {
        return Subscription::forFacility($facilityId)
            ->where(function ($query) {
                $query->where('status', SubscriptionStatus::ACTIVE->value)
                    ->orWhere(function ($q) {
                        $q->where('status', SubscriptionStatus::TRIAL->value)
                          ->where('trial_ends_at', '>', now());
                    })
                    ->orWhere(function ($q) {
                        $q->where('status', SubscriptionStatus::PAST_DUE->value)
                          ->where('grace_period_ends_at', '>', now());
                    });
            })
            ->with(['plan'])
            ->latest()
            ->first();
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['facility', 'plan'])
            ->withCount([
                'payments as pending_payments_count' => fn ($q) => $q->where(
                    'status',
                    PaymentStatus::PENDING->value,
                ),
                'payments as approved_payments_count' => fn ($q) => $q->where(
                    'status',
                    PaymentStatus::APPROVED->value,
                ),
            ])
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['facility_id']),
                fn($q) => $q->where('facility_id', $filters['facility_id'])
            )
            ->when(
                isset($filters['plan_id']),
                fn($q) => $q->where('plan_id', $filters['plan_id'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Subscription
    {
        return Subscription::create($data);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);
        return $subscription->fresh();
    }

    /** Subscriptions past their billing date not yet marked past_due. */
    public function getSubscriptionsNeedingGrace(): Collection
    {
        return Subscription::pendingGracePeriod()->get();
    }

    /** Past-due subscriptions whose 7-day grace window has closed. */
    public function getSubscriptionsGraceExpired(): Collection
    {
        return Subscription::graceExpired()->get();
    }

    /** Trial subscriptions that have expired. */
    public function getTrialSubscriptionsExpired(): Collection
    {
        return Subscription::where('status', SubscriptionStatus::TRIAL->value)
            ->where('trial_ends_at', '<', now())
            ->get();
    }

    public function hasEverHadTrial(int $facilityId): bool
    {
        return Subscription::where('facility_id', $facilityId)
            ->whereNotNull('trial_ends_at')
            ->withTrashed()
            ->exists();
    }
}
