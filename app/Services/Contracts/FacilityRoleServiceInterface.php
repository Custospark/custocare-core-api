<?php

namespace App\Services\Contracts;

use App\Models\FacilityRole;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FacilityRoleServiceInterface
{
    /**
     * Get all facility roles with pagination.
     */
    public function getAllRoles(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get facility role by ID.
     */
    public function getRoleById(int $id): ?FacilityRole;

    /**
     * Get facility role by code.
     */
    public function getRoleByCode(string $code): ?FacilityRole;

    /**
     * Create a new facility role.
     */
    public function createRole(array $data): FacilityRole;

    /**
     * Update an existing facility role.
     */
    public function updateRole(int $id, array $data): FacilityRole;

    /**
     * Delete a facility role.
     */
    public function deleteRole(int $id): bool;

    /**
     * Get roles by facility.
     */
    public function getRolesByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get roles by category.
     */
    public function getRolesByCategory(string $category): Collection;

    /**
     * Get active roles.
     */
    public function getActiveRoles(): Collection;

    /**
     * Toggle role active status.
     */
    public function toggleRoleStatus(int $id): FacilityRole;

    /**
     * Assign permissions to role.
     */
    public function assignPermissions(int $roleId, array $permissions): FacilityRole;

    /**
     * Get permissions for role.
     */
    public function getRolePermissions(int $roleId): array;

    /**
     * Get system roles.
     */
    public function getSystemRoles(): Collection;

    /**
     * Get custom roles.
     */
    public function getCustomRoles(): Collection;

    /**
     * Search roles by name or code.
     */
    public function searchRoles(string $query, array $filters = []): Collection;
}