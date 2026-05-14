<?php

namespace App\Services;

use App\Http\Resources\AmbulanceCrewMemberResource;
use App\Http\Resources\AmbulanceCrewMemberCollection;
use App\Repositories\Interfaces\AmbulanceCrewMemberRepositoryInterface;
use App\Services\Interfaces\AmbulanceCrewMemberServiceInterface;

class AmbulanceCrewMemberService implements AmbulanceCrewMemberServiceInterface
{
    public function __construct(protected AmbulanceCrewMemberRepositoryInterface $repository) {}

    public function getByAmbulance(int $ambulanceId): AmbulanceCrewMemberCollection
    {
        return new AmbulanceCrewMemberCollection($this->repository->getByAmbulance($ambulanceId));
    }

    public function getByStaff(int $staffId): AmbulanceCrewMemberCollection
    {
        return new AmbulanceCrewMemberCollection($this->repository->getByStaff($staffId));
    }

    public function create(array $data): AmbulanceCrewMemberResource
    {
        return new AmbulanceCrewMemberResource($this->repository->create($data));
    }

    public function update(int $id, array $data): AmbulanceCrewMemberResource
    {
        return new AmbulanceCrewMemberResource($this->repository->update($id, $data));
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
