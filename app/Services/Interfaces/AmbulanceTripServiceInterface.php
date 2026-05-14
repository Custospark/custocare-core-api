<?php

namespace App\Services\Interfaces;

use App\Http\Resources\AmbulanceTripResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

interface AmbulanceTripServiceInterface
{
    public function getAll(array $filters = [], int $perPage = 15): ResourceCollection;
    public function getById(int $id): AmbulanceTripResource;
    public function getByUuid(string $uuid): AmbulanceTripResource;
    public function create(array $data): AmbulanceTripResource;
    public function update(int $id, array $data): AmbulanceTripResource;
    public function delete(int $id): bool;
    public function getActive(array $filters = [], int $perPage = 15): ResourceCollection;
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 15): ResourceCollection;
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): ResourceCollection;
    public function getFromFacility(int $facilityId, array $filters = [], int $perPage = 15): ResourceCollection;
    public function getToFacility(int $facilityId, array $filters = [], int $perPage = 15): ResourceCollection;
    public function dispatchTrip(int $id): AmbulanceTripResource;
    public function markEnRoute(int $id): AmbulanceTripResource;
    public function markOnScene(int $id): AmbulanceTripResource;
    public function markPatientContact(int $id): AmbulanceTripResource;
    public function markDepartScene(int $id): AmbulanceTripResource;
    public function markAtDestination(int $id): AmbulanceTripResource;
    public function markCompleted(int $id): AmbulanceTripResource;
    public function cancelTrip(int $id, ?string $reason = null): AmbulanceTripResource;
}
