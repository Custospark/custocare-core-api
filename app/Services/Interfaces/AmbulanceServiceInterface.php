<?php

namespace App\Services\Interfaces;

use App\Http\Resources\AmbulanceResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

interface AmbulanceServiceInterface
{
    public function getAll(array $filters = [], int $perPage = 15): ResourceCollection;
    public function getById(int $id): AmbulanceResource;
    public function getByUuid(string $uuid): AmbulanceResource;
    public function create(array $data): AmbulanceResource;
    public function update(int $id, array $data): AmbulanceResource;
    public function delete(int $id): bool;
    public function getAvailable(array $filters = [], int $perPage = 15): ResourceCollection;
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): ResourceCollection;
}
