<?php

declare(strict_types=1);

namespace App\Repositories\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Models\Payment;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment
    {
        return Payment::with(['subscription', 'facility', 'approvedBy'])->find($id);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment->fresh();
    }

    public function getForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payment::forFacility($facilityId)
            ->with(['subscription.plan'])
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['payment_type']),
                fn($q) => $q->where('payment_type', $filters['payment_type'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function getForSubscription(int $subscriptionId): Collection
    {
        return Payment::where('subscription_id', $subscriptionId)
            ->with(['approvedBy'])
            ->latest()
            ->get();
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['facility', 'subscription.plan', 'approvedBy'])
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['facility_id']),
                fn($q) => $q->where('facility_id', $filters['facility_id'])
            )
            ->when(
                isset($filters['payment_type']),
                fn($q) => $q->where('payment_type', $filters['payment_type'])
            )
            ->when(
                isset($filters['method']),
                fn($q) => $q->where('method', $filters['method'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function getPendingCount(): int
    {
        return Payment::pending()->count();
    }

    public function findPendingByFacility(int $facilityId): array
    {
        return Payment::whereHas('subscription', function ($q) use ($facilityId) {
            $q->where('facility_id', $facilityId);
        })->pending()->get()->all();
    }
}
