<?php

namespace App\Services;

use App\Http\Resources\AmbulanceTripLogResource;
use App\Http\Resources\AmbulanceTripLogCollection;
use App\Repositories\Interfaces\AmbulanceTripLogRepositoryInterface;
use App\Services\Interfaces\AmbulanceTripLogServiceInterface;

class AmbulanceTripLogService implements AmbulanceTripLogServiceInterface
{
    public function __construct(protected AmbulanceTripLogRepositoryInterface $repository) {}

    public function getByTrip(int $tripId): AmbulanceTripLogCollection
    {
        return new AmbulanceTripLogCollection($this->repository->getByTrip($tripId));
    }

    public function create(array $data): AmbulanceTripLogResource
    {
        return new AmbulanceTripLogResource($this->repository->create($data));
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
