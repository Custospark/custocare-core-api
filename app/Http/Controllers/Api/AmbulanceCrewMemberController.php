<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmbulanceCrewMember\StoreAmbulanceCrewMemberRequest;
use App\Http\Requests\AmbulanceCrewMember\UpdateAmbulanceCrewMemberRequest;
use App\Http\Resources\AmbulanceCrewMemberResource;
use App\Http\Resources\AmbulanceCrewMemberCollection;
use App\Services\Interfaces\AmbulanceCrewMemberServiceInterface;
use Illuminate\Http\Response;

class AmbulanceCrewMemberController extends Controller
{
    public function __construct(protected AmbulanceCrewMemberServiceInterface $crewService) {}

    public function byAmbulance(int $ambulanceId): AmbulanceCrewMemberCollection
    {
        return $this->crewService->getByAmbulance($ambulanceId);
    }

    public function byStaff(int $staffId): AmbulanceCrewMemberCollection
    {
        return $this->crewService->getByStaff($staffId);
    }

    public function store(StoreAmbulanceCrewMemberRequest $request): AmbulanceCrewMemberResource
    {
        return $this->crewService->create($request->validated());
    }

    public function update(int $id, UpdateAmbulanceCrewMemberRequest $request): AmbulanceCrewMemberResource
    {
        return $this->crewService->update($id, $request->validated());
    }

    public function destroy(int $id): Response
    {
        $this->crewService->delete($id);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
