<?php

namespace App\Repositories\FacilityRoles;

use App\Models\FacilityRole;
use App\Repositories\Contracts\FacilityRoleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacilityRoleRepository 
{
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return FacilityRole::query()
            ->when($filters['facility_id'] ?? null, fn ($q, $facilityId) =>
                $q->where('facility_id', $facilityId)
            )
            ->when($filters['category'] ?? null, fn ($q, $category) =>
                $q->where('category', $category)
            )
            ->when(isset($filters['is_active']), fn ($q) =>
                $q->where('is_active', $filters['is_active'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?FacilityRole
    {
        return FacilityRole::find($id);
    }

    public function findByCode(string $code): ?FacilityRole
    {
        return FacilityRole::where('code', $code)->first();
    }
    public function query(): Builder
        {
            return FacilityRole::query();
        }

    public function codeExists(string $code): bool
    {
        return FacilityRole::where('code', $code)->exists();
    }

    public function create(array $data): FacilityRole
    {
        return FacilityRole::create($data);
    }

    public function update(int $id, array $data): FacilityRole
    {
        $role = FacilityRole::findOrFail($id);
        $role->update($data);

        return $role->refresh();
    }

    public function delete(int $id): bool
    {
        return (bool) FacilityRole::where('id', $id)->delete();
    }

    public function hasAssignedStaff(int $roleId): bool
    {
        return DB::table('facility_staff_roles')
            ->where('facility_role_id', $roleId)
            ->exists();
    }

    public function getByFacility(int $facilityId, array $filters = []): Collection
    {
        return FacilityRole::query()
            ->where('facility_id', $facilityId)
            ->when(isset($filters['is_active']), fn ($q) =>
                $q->where('is_active', $filters['is_active'])
            )
            ->orderBy('name')
            ->get();
    }

    public function getByCategory(string $category): Collection
    {
        return FacilityRole::where('category', $category)
            ->orderBy('name')
            ->get();
    }

    public function getActive(): Collection
    {
        return FacilityRole::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getSystemRoles(): Collection
    {
        return FacilityRole::where('is_system_role', true)
            ->orderBy('name')
            ->get();
    }

    public function getCustomRoles(): Collection
    {
        return FacilityRole::where('is_system_role', false)
            ->orderBy('name')
            ->get();
    }

    public function search(string $query, array $filters = []): Collection
    {
        return FacilityRole::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('code', 'LIKE', "%{$query}%");
            })
            ->when($filters['facility_id'] ?? null, fn ($q, $facilityId) =>
                $q->where('facility_id', $facilityId)
            )
            ->limit(50)
            ->get();
    }
}
