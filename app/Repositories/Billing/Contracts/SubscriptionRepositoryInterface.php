<?php

declare(strict_types=1);

namespace App\Repositories\Billing\Contracts;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionRepositoryInterface
{
    public function findById(int $id): ?Subscription;
    public function findByFacility(int $facilityId): ?Subscription;
    public function findActiveByFacility(int $facilityId): ?Subscription;
    public function findAccessibleByFacility(int $facilityId): ?Subscription;
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Subscription;
    public function update(Subscription $subscription, array $data): Subscription;
    public function getSubscriptionsNeedingGrace(): Collection;
    public function getSubscriptionsGraceExpired(): Collection;
    public function getTrialSubscriptionsExpired(): Collection;
    public function hasEverHadTrial(int $facilityId): bool;
    public function getRemainingTrialDays(int $facilityId, int $planTrialDays): int;
}
