<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PrescriptionRepositoryInterface
{
    public function all(array $filters = []): Collection;
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;
    public function find(int $id): ?object;
    public function findWithItems(int $id): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getByPatient(int $patientId, array $statuses = []): Collection;
    public function getByVisit(int $visitId): Collection;
    public function getActivePrescriptions(int $patientId): Collection;
    public function getReadyForBilling(int $patientId): Collection;
    public function updateStatus(int $id, string $status): bool;
    public function markAsDispensed(int $id, array $dispensingData): bool;
    public function cancel(int $id, string $reason, int $cancelledBy, ?string $notes = null): bool;
    public function generatePrescriptionNumber(int $facilityId): string;
}