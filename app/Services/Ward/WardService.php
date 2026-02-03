<?php

namespace App\Services\Ward;

use App\Models\Ward;

class WardService
{
    public function list(int $facilityId, array $filters = [])
    {
        $q = Ward::query()->where('facility_id', $facilityId);

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['ward_type'])) {
            $q->where('ward_type', $filters['ward_type']);
        }

        if (!empty($filters['search'])) {
            $term = trim((string)$filters['search']);
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('building', 'like', "%{$term}%")
                    ->orWhere('floor', 'like', "%{$term}%");
            });
        }

        return $q->orderBy('name')->get();
    }

    public function create(array $data, int $userId): Ward
    {
        $data['created_by_user_id'] = $userId;
        $data['updated_by_user_id'] = $userId;

        // defaults (if not provided)
        $data['status'] = $data['status'] ?? 'active';
        $data['sex_restriction'] = $data['sex_restriction'] ?? 'mixed';
        $data['age_group'] = $data['age_group'] ?? 'all';
        $data['ward_type'] = $data['ward_type'] ?? 'general';

        return Ward::create($data);
    }

    public function update(Ward $ward, array $data, int $userId): Ward
    {
        $data['updated_by_user_id'] = $userId;

        $ward->update($data);
        return $ward->refresh();
    }

    public function delete(Ward $ward): void
    {
        $ward->delete();
    }

    public function ensureFacilityScope(Ward $ward, int $facilityId): void
    {
        if ((int)$ward->facility_id !== (int)$facilityId) {
            throw new \Exception('Facility scope mismatch.');
        }
    }
}
