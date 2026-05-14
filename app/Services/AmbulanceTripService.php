<?php

namespace App\Services;

use App\Http\Resources\AmbulanceTripResource;
use App\Http\Resources\AmbulanceTripCollection;
use App\Repositories\Interfaces\AmbulanceTripRepositoryInterface;
use App\Services\Interfaces\AmbulanceTripServiceInterface;

class AmbulanceTripService implements AmbulanceTripServiceInterface
{
    public function __construct(protected AmbulanceTripRepositoryInterface $repository) {}

    public function getAll(array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getAll($filters, $perPage));
    }

    public function getById(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->getById($id));
    }

    public function getByUuid(string $uuid): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->getByUuid($uuid));
    }

    public function create(array $data): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->create($data));
    }

    public function update(int $id, array $data): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->update($id, $data));
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getActive(array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getActive($filters, $perPage));
    }

    public function getByPatient(int $patientId, array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getByPatient($patientId, $filters, $perPage));
    }

    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getByFacility($facilityId, $filters, $perPage));
    }

    public function getFromFacility(int $facilityId, array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getFromFacility($facilityId, $filters, $perPage));
    }

    public function getToFacility(int $facilityId, array $filters = [], int $perPage = 15): AmbulanceTripCollection
    {
        return new AmbulanceTripCollection($this->repository->getToFacility($facilityId, $filters, $perPage));
    }

    public function dispatchTrip(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->dispatchTrip($id));
    }

    public function markEnRoute(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markEnRoute($id));
    }

    public function markOnScene(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markOnScene($id));
    }

    public function markPatientContact(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markPatientContact($id));
    }

    public function markDepartScene(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markDepartScene($id));
    }

    public function markAtDestination(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markAtDestination($id));
    }

    public function markCompleted(int $id): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->markCompleted($id));
    }

    public function cancelTrip(int $id, ?string $reason = null): AmbulanceTripResource
    {
        return new AmbulanceTripResource($this->repository->cancelTrip($id, $reason));
    }
}
