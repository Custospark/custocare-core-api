<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ambulance\StoreAmbulanceRequest;
use App\Http\Requests\Ambulance\UpdateAmbulanceRequest;
use App\Http\Resources\AmbulanceResource;
use App\Http\Resources\AmbulanceCollection;
use App\Services\Interfaces\AmbulanceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AmbulanceController extends Controller
{
    public function __construct(protected AmbulanceServiceInterface $ambulanceService) {}

    public function index(Request $request): AmbulanceCollection
    {
        $filters = $request->only(['status', 'vehicle_type', 'equipment_level', 'search']);
        return $this->ambulanceService->getAll($filters, $request->integer('per_page', 15));
    }

    public function store(StoreAmbulanceRequest $request): AmbulanceResource
    {
        return $this->ambulanceService->create($request->validated());
    }

    public function show(string $uuid): AmbulanceResource
    {
        return $this->ambulanceService->getByUuid($uuid);
    }

    public function update(UpdateAmbulanceRequest $request, string $uuid): AmbulanceResource
    {
        $ambulance = $this->ambulanceService->getByUuid($uuid);
        return $this->ambulanceService->update($ambulance->id, $request->validated());
    }

    public function destroy(string $uuid): Response
    {
        $ambulance = $this->ambulanceService->getByUuid($uuid);
        $this->ambulanceService->delete($ambulance->id);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function available(Request $request): AmbulanceCollection
    {
        return $this->ambulanceService->getAvailable($request->only(['vehicle_type']), $request->integer('per_page', 15));
    }

    public function byFacility(int $facilityId, Request $request): AmbulanceCollection
    {
        return $this->ambulanceService->getByFacility($facilityId, $request->only(['status', 'vehicle_type']), $request->integer('per_page', 15));
    }
}
