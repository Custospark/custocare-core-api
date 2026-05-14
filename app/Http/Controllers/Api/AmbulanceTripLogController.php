<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmbulanceTripLog\StoreAmbulanceTripLogRequest;
use App\Http\Resources\AmbulanceTripLogResource;
use App\Http\Resources\AmbulanceTripLogCollection;
use App\Services\Interfaces\AmbulanceTripLogServiceInterface;
use App\Services\Interfaces\AmbulanceTripServiceInterface;
use Illuminate\Http\Response;

class AmbulanceTripLogController extends Controller
{
    public function __construct(
        protected AmbulanceTripLogServiceInterface $logService,
        protected AmbulanceTripServiceInterface $tripService,
    ) {}

    public function index(string $tripUuid): AmbulanceTripLogCollection
    {
        $trip = $this->tripService->getByUuid($tripUuid);
        return $this->logService->getByTrip($trip->id);
    }

    public function store(StoreAmbulanceTripLogRequest $request, string $tripUuid): AmbulanceTripLogResource
    {
        $trip = $this->tripService->getByUuid($tripUuid);
        $data = array_merge($request->validated(), ['trip_id' => $trip->id]);
        return $this->logService->create($data);
    }

    public function destroy(string $tripUuid, int $logId): Response
    {
        $this->logService->delete($logId);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
