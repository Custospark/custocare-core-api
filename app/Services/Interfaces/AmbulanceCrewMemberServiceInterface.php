<?php

namespace App\Services\Interfaces;

use App\Http\Resources\AmbulanceCrewMemberResource;
use App\Http\Resources\AmbulanceCrewMemberCollection;

interface AmbulanceCrewMemberServiceInterface
{
    public function getByAmbulance(int $ambulanceId): AmbulanceCrewMemberCollection;
    public function getByStaff(int $staffId): AmbulanceCrewMemberCollection;
    public function create(array $data): AmbulanceCrewMemberResource;
    public function update(int $id, array $data): AmbulanceCrewMemberResource;
    public function delete(int $id): bool;
}
