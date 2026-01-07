<?php

namespace App\Services;

use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\FacilityStaffRole;
use App\Models\RoleModuleDefault;
use App\Models\Module;
use Illuminate\Support\Collection;

class UserContextResolverService
{
    /**
     * Resolve user context with capability-based module access
     * 
     * STRICTLY FOLLOWS:
     * - Capabilities are the source of truth
     * - NO merging of capabilities
     * - NO global module access
     * - Facility admin overrides defaults completely
     */
    public function resolve(int $userId): array
    {
        $user = User::findOrFail($userId);

        // Get all active modules as source of truth
        $allModules = $this->getAllActiveModules();

        // Resolve ALL capabilities with their modules
        $capabilities = $this->resolveAllCapabilities($user, $allModules);

        // Facility roles (legacy support, only for staff with facilities)
        $facilityRoles = $this->resolveFacilityRoles($userId);

        return [
            'user' => $this->minimalUserData($user),
            'capabilities' => $capabilities,
            'facility_roles' => $facilityRoles,
        ];
    }

    /**
     * Return minimal user info for context
     */
    protected function minimalUserData(User $user): array
    {
          return [
            'id' => $user->id,
            'uuid' => $user->global_user_uuid,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email_encrypted ? decrypt($user->email_encrypted) : null,
            'phone' => $user->phone_encrypted ? decrypt($user->phone_encrypted) : null,
            'national_id_country_code' => $user->national_id_country_code,
        ];
    }

    /**
     * Get all active modules from database
     */
    protected function getAllActiveModules(): Collection
    {
        return Module::where('is_active', true)
            ->select(['id', 'code', 'name', 'description'])
            ->get()
            ->keyBy('code');
    }

    /**
     * Resolve ALL capabilities with their modules
     */
    protected function resolveAllCapabilities(User $user, Collection $allModules): array
    {
        $capabilities = [];

        // 1. PATIENT capability (if patient record exists)
        $patient = Patient::where('user_id', $user->id)->first();
        if ($patient) {
            $capabilities['patient'] = [
                'patient_id' => $patient->id,
                'primary_facility_id' => $patient->primary_facility_id,
                'medical_record_number' => $patient->medical_record_number,
                'modules' => $this->resolvePatientModules($allModules),
            ];
        }

        // 2. STAFF capability (if staff record exists - ONLY ONE)
        $staff = Staff::where('user_id', $user->id)->first();
        if ($staff) {
            $hasFacilityAssignments = FacilityStaffRole::where('staff_id', $staff->id)
                ->where('assignment_status', 'active')
                ->where(function($query) {
                    $query->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now());
                })
                ->exists();

            $capabilities['staff'] = [
                'staff_id' => $staff->id,
                'employee_id' => $staff->employee_id,
                'professional_title' => $staff->professional_title,
            ];

            if ($hasFacilityAssignments) {
                // Staff WITH facilities - modules come ONLY from facility_staff_roles
                $capabilities['staff']['facilities'] = $this->resolveStaffFacilitiesWithModules(
                    $staff->id, 
                    $allModules
                );
            } else {
                // Staff WITHOUT facilities - modules come ONLY from role_module_defaults
                $capabilities['staff']['facilities'] = [];
                $capabilities['staff']['modules'] = $this->resolveStaffModules($allModules);
            }
        }

        // 3. SPATIE ROLES capabilities (each is standalone)
        $spatieRoles = $user->getRoleNames();
        foreach ($spatieRoles as $roleName) {
            $capabilities[$roleName] = [
                'modules' => $this->resolveSpatieRoleModules($roleName, $allModules),
            ];
        }

        return $capabilities;
    }

    /**
     * Resolve patient modules from role_module_defaults
     */
    protected function resolvePatientModules(Collection $allModules): array
    {
        $patientAccess = RoleModuleDefault::where('role_code', 'patient')
            ->where('default_access', true)
            ->whereIn('module_code', $allModules->keys())
            ->pluck('module_code')
            ->toArray();

        return $this->buildModuleList($allModules, $patientAccess);
    }

    /**
     * Resolve staff modules from role_module_defaults (staff without facilities)
     */
    protected function resolveStaffModules(Collection $allModules): array
    {
        $staffAccess = RoleModuleDefault::where('role_code', 'staff')
            ->where('default_access', true)
            ->whereIn('module_code', $allModules->keys())
            ->pluck('module_code')
            ->toArray();

        return $this->buildModuleList($allModules, $staffAccess);
    }

    /**
     * Resolve Spatie role modules from role_module_defaults
     */
    protected function resolveSpatieRoleModules(string $roleName, Collection $allModules): array
    {
        $roleAccess = RoleModuleDefault::where('role_code', $roleName)
            ->where('default_access', true)
            ->whereIn('module_code', $allModules->keys())
            ->pluck('module_code')
            ->toArray();

        return $this->buildModuleList($allModules, $roleAccess);
    }

    /**
     * Resolve staff facilities with their modules
     */
    protected function resolveStaffFacilitiesWithModules(int $staffId, Collection $allModules): array
    {
        $facilityRoles = FacilityStaffRole::with('facility')
            ->where('staff_id', $staffId)
            ->where('assignment_status', 'active')
            ->where(function($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now());
            })
            ->get();

        $facilities = [];

        foreach ($facilityRoles as $role) {
            $moduleCodes = $this->extractModuleCodes($role->module_code);
            
            $facilities[] = [
                'facility_id' => $role->facility_id,
                'facility_name' => $role->facility->facility_name ?? null,
                'role_code' => $role->role_code,
                'modules' => $this->buildModuleList($allModules, $moduleCodes),
            ];
        }

        return $facilities;
    }

    /**
     * Extract module codes from JSON column
     */
    protected function extractModuleCodes($moduleCodeField): array
    {
        if (is_string($moduleCodeField)) {
            $decoded = json_decode($moduleCodeField, true) ?? [];
        } elseif (is_array($moduleCodeField)) {
            $decoded = $moduleCodeField;
        } else {
            $decoded = [];
        }

        // Return only valid, non-empty module codes
        return array_values(array_filter($decoded, function($item) {
            return is_string($item) && !empty(trim($item));
        }));
    }

    /**
     * Build module list with has_access flag
     */
    protected function buildModuleList(Collection $allModules, array $accessibleCodes): array
    {
        $modules = [];

        foreach ($allModules as $module) {
            $modules[] = [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'is_active' => in_array($module->code, $accessibleCodes),
            ];
        }

        return $modules;
    }

    /**
     * Resolve facility roles (legacy support)
     */
    protected function resolveFacilityRoles(int $userId): array
    {
        $staff = Staff::where('user_id', $userId)->first();
        if (!$staff) return [];

        $roles = FacilityStaffRole::with('facility')
            ->where('staff_id', $staff->id)
            ->where('assignment_status', 'active')
            ->where(function($query) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', now());
            })
            ->get();

        return $roles->map(function ($role) {
            return [
                'facility_id' => $role->facility_id,
                'facility_name' => $role->facility->facility_name ?? null,
                'role_code' => $role->role_code,
                'is_primary_facility' => $role->is_primary_facility,
            ];
        })->toArray();
    }

    /**
     * Check if user can access a module in a specific capability
     */
    public function canAccessInCapability(
        int $userId, 
        string $capability, 
        string $moduleCode, 
        ?int $facilityId = null
    ): bool {
        $context = $this->resolve($userId);
        
        if (!isset($context['capabilities'][$capability])) {
            return false;
        }

        $capabilityData = $context['capabilities'][$capability];

        // Handle staff with facilities
        if ($capability === 'staff' && isset($capabilityData['facilities'])) {
            if ($facilityId === null) {
                // Staff without facilities
                $modules = $capabilityData['modules'] ?? [];
            } else {
                // Find specific facility
                $facility = collect($capabilityData['facilities'])
                    ->firstWhere('facility_id', $facilityId);
                
                if (!$facility) return false;
                
                $modules = $facility['modules'] ?? [];
            }
        } else {
            // Patient or Spatie role
            $modules = $capabilityData['modules'] ?? [];
        }

        foreach ($modules as $module) {
            if ($module['code'] === $moduleCode && $module['is_active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get accessible modules for a specific capability
     */
    public function getAccessibleModulesInCapability(
        int $userId, 
        string $capability, 
        ?int $facilityId = null
    ): array {
        $context = $this->resolve($userId);
        
        if (!isset($context['capabilities'][$capability])) {
            return [];
        }

        $capabilityData = $context['capabilities'][$capability];

        if ($capability === 'staff' && isset($capabilityData['facilities'])) {
            if ($facilityId === null) {
                // Staff without facilities
                $modules = $capabilityData['modules'] ?? [];
            } else {
                // Find specific facility
                $facility = collect($capabilityData['facilities'])
                    ->firstWhere('facility_id', $facilityId);
                
                if (!$facility) return [];
                
                $modules = $facility['modules'] ?? [];
            }
        } else {
            // Patient or Spatie role
            $modules = $capabilityData['modules'] ?? [];
        }

        // Return only accessible modules
        return array_values(array_filter($modules, function($module) {
            return $module['is_active'] === true;
        }));
    }
}