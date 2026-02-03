<?php

namespace App\Services\FacilitySpaceService;

use App\Models\FacilitySpace;

class FacilitySpaceService
{
    public function listSpaces(int $facilityId, bool $activeOnly = false)
    {
        $q = FacilitySpace::query()->where('facility_id', $facilityId);

        if ($activeOnly) {
            $q->active();
        }

        return $q->orderBy('type')
            ->orderBy('building')
            ->orderBy('floor')
            ->orderBy('name')
            ->get();
    }

    public function createSpace(array $data): FacilitySpace
    {
        return FacilitySpace::create($data);
    }

    public function updateSpace(FacilitySpace $space, array $data): FacilitySpace
    {
        $space->update($data);
        return $space->refresh();
    }

    public function deleteSpace(FacilitySpace $space): void
{
    $space->delete();
    
}
}
