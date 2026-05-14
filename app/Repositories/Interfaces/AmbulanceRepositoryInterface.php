<?php

namespace App\Repositories\Interfaces;

use App\Models\Ambulance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AmbulanceRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): Ambulance;
    public function getByUuid(string $uuid): Ambulance;
    public function create(array $data): Ambulance;
    public function update(int $id, array $data): Ambulance;
    public function delete(int $id): bool;
    public function getAvailable(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
