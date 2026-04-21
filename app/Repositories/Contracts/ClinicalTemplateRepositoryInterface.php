<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClinicalTemplateRepositoryInterface
{
    public function all(array $filters = []): Collection;
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;
    public function find(int $id): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getByFacility(int $facilityId, bool $includeSystem = true): Collection;
    public function getByCategory(string $category, int $facilityId): Collection;
    public function incrementUsage(int $id): bool;
    public function toggleStatus(int $id): bool;
    public function search(string $keyword, int $facilityId): Collection;
}