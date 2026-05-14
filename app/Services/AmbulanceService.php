<?php

namespace App\Services;

use App\Http\Resources\AmbulanceResource;
use App\Http\Resources\AmbulanceCollection;
use App\Repositories\Interfaces\AmbulanceRepositoryInterface;
use App\Services\Interfaces\AmbulanceServiceInterface;

class AmbulanceService implements AmbulanceServiceInterface
{
    public function __construct(protected AmbulanceRepositoryInterface $repository) {}

    public function getAll(array $filters = [], int $perPage = 15): AmbulanceCollection
    {
        return new AmbulanceCollection($this->repository->getAll($filters, $perPage));
    }

    public function getById(int $id): AmbulanceResource
    {
        return new AmbulanceResource($this->repository->getById($id));
    }

    public function getByUuid(string $uuid): AmbulanceResource
    {
        return new AmbulanceResource($this->repository->getByUuid($uuid));
    }

    public function create(array $data): AmbulanceResource
    {
        return new AmbulanceResource($this->repository->create($data));
    }

    public function update(int $id, array $data): AmbulanceResource
    {
        return new AmbulanceResource($this->repository->update($id, $data));
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getAvailable(array $filters = [], int $perPage = 15): AmbulanceCollection
    {
        return new AmbulanceCollection($this->repository->getAvailable($filters, $perPage));
    }

    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): AmbulanceCollection
    {
        return new AmbulanceCollection($this->repository->getByFacility($facilityId, $filters, $perPage));
    }
}
