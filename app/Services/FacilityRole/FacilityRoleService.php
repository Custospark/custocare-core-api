<?php

namespace App\Services\FacilityRole;

use App\Models\FacilityRole;

use App\Repositories\FacilityRoles\FacilityRoleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FacilityRoleService 
{
    /**
     * The repository instance.
     */
    protected FacilityRoleRepository $repository;

    /**
     * Create a new service instance.
     */
    public function __construct(FacilityRoleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all facility roles with pagination.
     */

public function getAllRoles(array $filters = []): Builder
{
    $query = $this->repository->query(); // Staff or FacilityRole model query

    // Only system roles if provided
    if (isset($filters['is_system_role'])) {
        $query->where('is_system_role', $filters['is_system_role']);
    }

    // Filter by facility
    if (!empty($filters['facility_id'])) {
        $query->where('facility_id', $filters['facility_id']);
    }

    // Filter by active status
    if (isset($filters['is_active'])) {
        $query->where('is_active', $filters['is_active']);
    }

    // Search by name
    if (!empty($filters['search'])) {
        $query->where('name', 'like', '%' . $filters['search'] . '%');
    }

    // Sorting
    $sortBy    = $filters['sort_by'] ?? 'name';
    $sortOrder = $filters['sort_order'] ?? 'asc';
    $query->orderBy($sortBy, $sortOrder);

    return $query;
}


    /**
     * Get facility role by ID.
     */
    public function getRoleById(int $id): ?FacilityRole
    {
        return $this->repository->findById($id);
    }

    /**
     * Get facility role by code.
     */
    public function getRoleByCode(string $code): ?FacilityRole
    {
        return $this->repository->findByCode($code);
    }

    /**
     * Create a new facility role.
     */
    public function createRole(array $data): FacilityRole
    {
        // Validate required fields
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Role name is required.');
        }

        // Generate slug if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateRoleCode($data['name']);
        }

        // Check for duplicate code
        if ($this->repository->codeExists($data['code'])) {
            throw new \Exception('A role with this code already exists.');
        }

        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_system_role'] = $data['is_system_role'] ?? false;

        return $this->repository->create($data);
    }

    /**
     * Update an existing facility role.
     */
    public function updateRole(int $id, array $data): FacilityRole
    {
        $existingRole = $this->repository->findById($id);
        
        if (!$existingRole) {
            throw new \Exception('Role not found.');
        }

        // Prevent updating system roles
        if ($existingRole->is_system_role && isset($data['code'])) {
            throw new \Exception('Cannot update code of a system role.');
        }

        // Prevent updating code if already in use by another role
        if (isset($data['code']) && $data['code'] !== $existingRole->code) {
            if ($this->repository->codeExists($data['code'])) {
                throw new \Exception('A role with this code already exists.');
            }
        }

        return $this->repository->update($id, $data);
    }

    /**
     * Delete a facility role.
     */
    public function deleteRole(int $id): bool
    {
        $existingRole = $this->repository->findById($id);
        
        if (!$existingRole) {
            throw new \Exception('Role not found.');
        }

        // Prevent deleting system roles
        if ($existingRole->is_system_role) {
            throw new \Exception('Cannot delete a system role.');
        }

        // Check if role is assigned to any staff
        if ($this->repository->hasAssignedStaff($id)) {
            throw new \Exception('Cannot delete role that is assigned to staff members.');
        }

        return $this->repository->delete($id);
    }

    /**
     * Get roles by facility.
     */
    public function getRolesByFacility(int $facilityId, array $filters = []): Collection
    {
        return $this->repository->getByFacility($facilityId, $filters);
    }

    /**
     * Get roles by category.
     */
    public function getRolesByCategory(string $category): Collection
    {
        return $this->repository->getByCategory($category);
    }

    /**
     * Get active roles.
     */
    public function getActiveRoles(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Toggle role active status.
     */
    public function toggleRoleStatus(int $id): FacilityRole
    {
        $role = $this->repository->findById($id);
        
        if (!$role) {
            throw new \Exception('Role not found.');
        }

        if ($role->is_system_role) {
            throw new \Exception('Cannot deactivate a system role.');
        }

        $role->is_active = !$role->is_active;
        $role->save();

        return $role;
    }

    /**
     * Assign permissions to role.
     */
    public function assignPermissions(int $roleId, array $permissions): FacilityRole
    {
        $role = $this->repository->findById($roleId);
        
        if (!$role) {
            throw new \Exception('Role not found.');
        }

        $role->permissions = $permissions;
        $role->save();

        return $role;
    }

    /**
     * Get permissions for role.
     */
    public function getRolePermissions(int $roleId): array
    {
        $role = $this->repository->findById($roleId);
        
        if (!$role) {
            throw new \Exception('Role not found.');
        }

        return $role->permissions ?? [];
    }

    /**
     * Generate role code from name.
     */
    protected function generateRoleCode(string $name): string
    {
        // Convert to uppercase, replace spaces with underscores, remove special chars
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '_', $name));
        
        // Remove consecutive underscores and trim
        $code = preg_replace('/_+/', '_', $code);
        $code = trim($code, '_');
        
        return $code;
    }

    /**
     * Get system roles.
     */
    public function getSystemRoles(): Collection
    {
        return $this->repository->getSystemRoles();
    }

    /**
     * Get custom roles.
     */
    public function getCustomRoles(): Collection
    {
        return $this->repository->getCustomRoles();
    }

    /**
     * Search roles by name or code.
     */
    public function searchRoles(string $query, array $filters = []): Collection
    {
        return $this->repository->search($query, $filters);
    }
}