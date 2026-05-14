<?php

namespace App\Repositories\Interfaces;

use App\Models\AmbulanceCrewMember;
use Illuminate\Database\Eloquent\Collection;

interface AmbulanceCrewMemberRepositoryInterface
{
    public function getByAmbulance(int $ambulanceId): Collection;
    public function getByStaff(int $staffId): Collection;
    public function create(array $data): AmbulanceCrewMember;
    public function update(int $id, array $data): AmbulanceCrewMember;
    public function delete(int $id): bool;
    public function getActiveByAmbulance(int $ambulanceId): Collection;
}
