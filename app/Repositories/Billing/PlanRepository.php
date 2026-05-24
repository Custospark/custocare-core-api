<?php

declare(strict_types=1);

namespace App\Repositories\Billing;

use App\Models\Plan;
use App\Repositories\Billing\Contracts\PlanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlanRepository implements PlanRepositoryInterface
{
    public function findById(int $id): ?Plan
    {
        return Plan::find($id);
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::where('slug', $slug)->first();
    }

    public function getAll(array $filters = []): Collection
    {
        return Plan::ordered()
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active']))
            ->get();
    }

    public function getAllActive(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Plan::query()
            ->when(isset($filters['is_active']), fn($q) => $q->where('is_active', $filters['is_active']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term));
            })
            ->ordered()
            ->paginate($perPage);
    }

    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);
        return $plan->fresh();
    }

    public function delete(Plan $plan): bool
    {
        return (bool) $plan->delete();
    }
}
