<?php

namespace App\Repositories\Interfaces;

use App\Models\AmbulanceTrip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AmbulanceTripRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): AmbulanceTrip;
    public function getByUuid(string $uuid): AmbulanceTrip;
    public function create(array $data): AmbulanceTrip;
    public function update(int $id, array $data): AmbulanceTrip;
    public function delete(int $id): bool;
    public function getActive(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getFromFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getToFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function dispatchTrip(int $id): AmbulanceTrip;
    public function markEnRoute(int $id): AmbulanceTrip;
    public function markOnScene(int $id): AmbulanceTrip;
    public function markPatientContact(int $id): AmbulanceTrip;
    public function markDepartScene(int $id): AmbulanceTrip;
    public function markAtDestination(int $id): AmbulanceTrip;
    public function markCompleted(int $id): AmbulanceTrip;
    public function cancelTrip(int $id, ?string $reason = null): AmbulanceTrip;
}
