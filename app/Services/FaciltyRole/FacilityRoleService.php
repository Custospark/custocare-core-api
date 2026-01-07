<?php

namespace App\Services\FaciltyRole;

use App\Models\FacilityRole;
use App\Models\Role;
use Illuminate\Support\Str;

class FacilityRoleService
{
    public function list(array $filters = [])
    {
        return FacilityRole::query()
            ->when(
                isset($filters['is_system_role']),
                fn ($q) => $q->where('is_system_role', $filters['is_system_role'])
            )
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): FacilityRole
    {
        return FacilityRole::create([
            'name'           => $data['name'],
            'code'           => $data['code'] ?? Str::slug($data['name']),
            'description'    => $data['description'] ?? null,
            'is_system_role' => $data['is_system_role'] ?? false,
        ]);
    }

    public function update(FacilityRole $role, array $data): FacilityRole
    {
        $role->update($data);
        return $role;
    }

    public function delete(FacilityRole $role): void
    {
        if ($role->is_system_role) {
            throw new \RuntimeException('System roles cannot be deleted.');
        }

        $role->delete();
    }
}
