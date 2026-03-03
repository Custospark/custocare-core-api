<?php

declare(strict_types=1);

namespace App\Repositories\Billing\Contracts;

use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PlanRepositoryInterface
{
    public function findById(int $id): ?Plan;
    public function findBySlug(string $slug): ?Plan;
    public function getAll(array $filters = []): Collection;
    public function getAllActive(): Collection;
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Plan;
    public function update(Plan $plan, array $data): Plan;
    public function delete(Plan $plan): bool;
}
