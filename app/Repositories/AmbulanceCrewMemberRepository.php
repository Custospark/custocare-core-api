<?php

namespace App\Repositories;

use App\Models\AmbulanceCrewMember;
use App\Repositories\Interfaces\AmbulanceCrewMemberRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AmbulanceCrewMemberRepository implements AmbulanceCrewMemberRepositoryInterface
{
    public function getByAmbulance(int $ambulanceId): Collection
    {
        return AmbulanceCrewMember::with(['staff'])
            ->where('ambulance_id', $ambulanceId)
            ->orderBy('created_at')
            ->get();
    }

    public function getByStaff(int $staffId): Collection
    {
        return AmbulanceCrewMember::with(['ambulance'])
            ->where('staff_id', $staffId)
            ->where('active', true)
            ->get();
    }

    public function create(array $data): AmbulanceCrewMember
    {
        if (!isset($data['assigned_at'])) {
            $data['assigned_at'] = now();
        }
        return AmbulanceCrewMember::create($data);
    }

    public function update(int $id, array $data): AmbulanceCrewMember
    {
        $member = AmbulanceCrewMember::findOrFail($id);
        $member->update($data);
        return $member->fresh();
    }

    public function delete(int $id): bool
    {
        return AmbulanceCrewMember::findOrFail($id)->delete();
    }

    public function getActiveByAmbulance(int $ambulanceId): Collection
    {
        return AmbulanceCrewMember::with(['staff'])
            ->where('ambulance_id', $ambulanceId)
            ->where('active', true)
            ->get();
    }
}
