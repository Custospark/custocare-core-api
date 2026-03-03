<?php

declare(strict_types=1);

namespace App\Repositories\Billing\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment;
    public function create(array $data): Payment;
    public function update(Payment $payment, array $data): Payment;
    public function getForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getForSubscription(int $subscriptionId): Collection;
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getPendingCount(): int;
}
