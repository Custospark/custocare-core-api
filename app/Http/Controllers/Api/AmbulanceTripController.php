<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmbulanceTrip\StoreAmbulanceTripRequest;
use App\Http\Requests\AmbulanceTrip\UpdateAmbulanceTripRequest;
use App\Http\Resources\AmbulanceTripResource;
use App\Http\Resources\AmbulanceTripCollection;
use App\Services\Interfaces\AmbulanceTripServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AmbulanceTripController extends Controller
{
    public function __construct(protected AmbulanceTripServiceInterface $tripService) {}

    public function index(Request $request): AmbulanceTripCollection
    {
        $filters = $request->only(['status', 'trip_type', 'priority', 'patient_id', 'ambulance_id', 'from_date', 'to_date', 'search']);
        return $this->tripService->getAll($filters, $request->integer('per_page', 15));
    }

    public function store(StoreAmbulanceTripRequest $request): AmbulanceTripResource
    {
        return $this->tripService->create($request->validated());
    }

    public function show(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->getByUuid($uuid);
    }

    public function update(UpdateAmbulanceTripRequest $request, string $uuid): AmbulanceTripResource
    {
        $trip = $this->tripService->getByUuid($uuid);
        return $this->tripService->update($trip->id, $request->validated());
    }

    public function destroy(string $uuid): Response
    {
        $trip = $this->tripService->getByUuid($uuid);
        $this->tripService->delete($trip->id);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // ─── Status transitions ───

    public function dispatch(string $uuid, Request $request): AmbulanceTripResource
    {
        $trip = $this->tripService->getByUuid($uuid);
        if ($ambulanceId = $request->input('ambulance_id')) {
            $this->tripService->update($trip->id, ['ambulance_id' => $ambulanceId]);
        }
        return $this->tripService->dispatchTrip($trip->id);
    }

    public function enRoute(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markEnRoute($this->tripService->getByUuid($uuid)->id);
    }

    public function onScene(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markOnScene($this->tripService->getByUuid($uuid)->id);
    }

    public function patientContact(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markPatientContact($this->tripService->getByUuid($uuid)->id);
    }

    public function departScene(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markDepartScene($this->tripService->getByUuid($uuid)->id);
    }

    public function atDestination(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markAtDestination($this->tripService->getByUuid($uuid)->id);
    }

    public function complete(string $uuid): AmbulanceTripResource
    {
        return $this->tripService->markCompleted($this->tripService->getByUuid($uuid)->id);
    }

    public function cancel(string $uuid, Request $request): AmbulanceTripResource
    {
        return $this->tripService->cancelTrip($this->tripService->getByUuid($uuid)->id, $request->input('reason'));
    }

    // ─── Special listings ───

    public function active(Request $request): AmbulanceTripCollection
    {
        return $this->tripService->getActive($request->only(['trip_type', 'priority']), $request->integer('per_page', 15));
    }

    public function byPatient(int $patientId, Request $request): AmbulanceTripCollection
    {
        return $this->tripService->getByPatient($patientId, $request->only(['status', 'trip_type']), $request->integer('per_page', 15));
    }

    public function byFacility(int $facilityId, Request $request): AmbulanceTripCollection
    {
        return $this->tripService->getByFacility($facilityId, $request->only(['status', 'trip_type']), $request->integer('per_page', 15));
    }

    public function fromFacility(int $facilityId, Request $request): AmbulanceTripCollection
    {
        return $this->tripService->getFromFacility($facilityId, $request->only(['status']), $request->integer('per_page', 15));
    }

    public function toFacility(int $facilityId, Request $request): AmbulanceTripCollection
    {
        return $this->tripService->getToFacility($facilityId, $request->only(['status']), $request->integer('per_page', 15));
    }
}
