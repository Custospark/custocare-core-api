<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface PrescriptionItemRepositoryInterface
{
    public function create(array $data): object;
    public function createMany(int $prescriptionId, array $items): Collection;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function deleteByPrescription(int $prescriptionId): bool;
    public function getByPrescription(int $prescriptionId): Collection;
}