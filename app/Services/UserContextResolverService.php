<?php

namespace App\Services;

use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\FacilityStaffRole;
use App\Models\RoleModuleDefault;
use App\Models\Module;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UserContextResolverService
{
    /**
     * Resolve full user context with properly resolved module access
     */
    public function resolve(int $userId): array
    {
        $user = User::findOrFail($userId);

        // Get all active modules with complete information
        $allModules = $this->getAllActiveModulesWithInfo();

        return [
            'user' => $this->minimalUserData($user),
            'capabilities' => $this->resolveCapabilities($userId),
            'facility_roles' => $this->resolveFacilityRoles($userId),
            'module_access' => $this->resolveModuleAccessByCapability($user, $allModules)
        ];
    }

    /**
     * Return minimal user info for context.
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
     * Resolve patient and staff capabilities.
     */
    protected function resolveCapabilities(int $userId): array
    {
        $capabilities = [];

        // Check if user is a patient
        $patient = Patient::where('user_id', $userId)->first();
        if ($patient) {
            $capabilities['patient'] = [
                'patient_id' => $patient->id,
                'primary_facility_id' => $patient->primary_facility_id,
                'medical_record_number' => $patient->medical_record_number,
            ];
        }

        // Check if user is a staff
        $staff = Staff::where('user_id', $userId)->first();
        if ($staff) {
            $capabilities['staff'] = [
                'staff_id' => $staff->id,
                'employee_id' => $staff->employee_id,
                'professional_title' => $staff->professional_title,
            ];
        }

        return $capabilities;
    }

    /**
     * Resolve active facility roles for staff.
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
                'facility_uuid' => $role->facility->uuid ?? null,
                'facility_name' => $role->facility->facility_name ?? null,
                'staff_id' => $role->staff_id,
                'role_code' => $role->role_code,
                'is_primary_facility' => $role->is_primary_facility,
                'module_codes' => $this->extractModuleCodes($role->module_code), // Add module codes to facility roles
                'effective_from' => $role->effective_from,
                'effective_to' => $role->effective_to,
            ];
        })->toArray();
    }

    /**
     * Extract module codes from the module_code JSON column
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

        // Return as simple array of module codes
        return array_values(array_filter($decoded, function($item) {
            return is_string($item) && !empty(trim($item));
        }));
    }

    /**
     * Get all active modules with full information
     */
    protected function getAllActiveModulesWithInfo(): Collection
    {
        return Module::where('is_active', true)
            ->select(['id', 'code', 'name', 'description'])
            ->get()
            ->keyBy('code');
    }

    /**
     * Resolve module access by capability type
     */
    protected function resolveModuleAccessByCapability(User $user, Collection $allModules): array
    {
        $access = [];

        // 1. Resolve module access for PATIENT capability
        if (Patient::where('user_id', $user->id)->exists()) {
            $patientRole = 'patient';
            $access['patient'] = [
                'role_code' => $patientRole,
                'role_name' => 'Patient',
                'modules' => $this->resolveModulesForRole($patientRole, $allModules)
            ];
        }

        // 2. Check if user has staff record
        $staff = Staff::where('user_id', $user->id)->first();
        if ($staff) {
            // Check if staff has facility assignments
            $hasFacilityAssignments = FacilityStaffRole::where('staff_id', $staff->id)
                ->where('assignment_status', 'active')
                ->exists();

            if (!$hasFacilityAssignments) {
                // 2a. Staff-only (no facility assignment)
                $staffRole = 'staff';
                $access['staff_only'] = [
                    'role_code' => $staffRole,
                    'role_name' => 'Unassigned Staff',
                    'modules' => $this->resolveModulesForRole($staffRole, $allModules)
                ];
            } else {
                // 2b. Staff with facilities - resolve per facility
                $facilityRoles = $this->resolveFacilityRoles($user->id);
                $access['staff_with_facilities'] = [];
                
                foreach ($facilityRoles as $facilityRole) {
                    $facilityId = $facilityRole['facility_id'];
                    $roleCode = $facilityRole['role_code'];
                    $moduleCodes = $facilityRole['module_codes'] ?? [];
                    
                    // Get facility-specific module access from module_code column
                    $facilityModuleAccess = $this->getFacilitySpecificModulesFromCodes(
                        $moduleCodes,
                        $allModules
                    );
                    
                    $access['staff_with_facilities'][$facilityId] = [
                        'facility_id' => $facilityId,
                        'facility_name' => $facilityRole['facility_name'],
                        'role_code' => $roleCode,
                        'role_name' => $this->getRoleDisplayName($roleCode),
                        'is_primary_facility' => $facilityRole['is_primary_facility'],
                        'assigned_module_codes' => $moduleCodes, // Include for debugging/reference
                        'modules' => $facilityModuleAccess
                    ];
                }
            }
        }

        // 3. Resolve Spatie role module access
        $spatieRoles = $user->getRoleNames();
        foreach ($spatieRoles as $roleName) {
            $access[$roleName] = [
                'role_code' => $roleName,
                'role_name' => $this->getRoleDisplayName($roleName),
                'modules' => $this->resolveModulesForRole($roleName, $allModules)
            ];
        }

        return $access;
    }

    /**
     * Resolve modules for a specific role (from role_module_defaults)
     */
    protected function resolveModulesForRole(string $roleCode, Collection $allModules): array
    {
        // Get default access for this role
        $defaultAccess = RoleModuleDefault::where('role_code', $roleCode)
            ->where('default_access', true)
            ->whereIn('module_code', $allModules->keys())
            ->pluck('module_code')
            ->toArray();

        $modules = [];
        foreach ($allModules as $module) {
            $modules[] = [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'has_access' => in_array($module->code, $defaultAccess)
            ];
        }

        return $modules;
    }

    /**
     * Get facility-specific module access from module_code column
     * This COMPLETELY OVERRIDES defaults - only modules in module_code have access
     */
    protected function getFacilitySpecificModulesFromCodes(
        array $assignedModuleCodes,
        Collection $allModules
    ): array {
        $modules = [];
        
        foreach ($allModules as $module) {
            $modules[] = [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'has_access' => in_array($module->code, $assignedModuleCodes)
            ];
        }

        return $modules;
    }

    /**
     * Get human-readable role name
     */
    protected function getRoleDisplayName(string $roleCode): string
    {
        $roleNames = [
            'patient' => 'Patient',
            'staff' => 'Staff',
            'physician' => 'Physician',
            'surgeon' => 'Surgeon',
            'anesthesiologist' => 'Anesthesiologist',
            'nurse' => 'Nurse',
            'nurse_manager' => 'Nurse Manager',
            'pharmacist' => 'Pharmacist',
            'pharmacy_technician' => 'Pharmacy Technician',
            'radiologist' => 'Radiologist',
            'radiology_technician' => 'Radiology Technician',
            'laboratory_scientist' => 'Laboratory Scientist',
            'respiratory_therapist' => 'Respiratory Therapist',
            'physical_therapist' => 'Physical Therapist',
            'occupational_therapist' => 'Occupational Therapist',
            'social_worker' => 'Social Worker',
            'case_manager' => 'Case Manager',
            'medical_assistant' => 'Medical Assistant',
            'receptionist' => 'Receptionist',
            'facility_administrator' => 'Facility Administrator',
            'department_manager' => 'Department Manager',
            'quality_coordinator' => 'Quality Coordinator',
            'infection_control' => 'Infection Control',
            'it_support' => 'IT Support',
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'regulator' => 'Regulator',
            'auditor' => 'Auditor',
        ];

        return $roleNames[$roleCode] ?? ucwords(str_replace('_', ' ', $roleCode));
    }

    /**
     * Get accessible modules for current context (helper for frontend)
     */
    public function getAccessibleModulesForContext(int $userId, string $contextType, ?int $facilityId = null): array
    {
        $context = $this->resolve($userId);
        
        if (!isset($context['module_access'][$contextType])) {
            return [];
        }
        
        // Handle staff with facilities (facility-specific)
        if ($contextType === 'staff_with_facilities' && $facilityId) {
            $facilityAccess = $context['module_access'][$contextType][$facilityId] ?? null;
            $modules = $facilityAccess['modules'] ?? [];
        } else {
            $modules = $context['module_access'][$contextType]['modules'] ?? [];
        }
        
        // Filter for only accessible modules
        return array_values(array_filter($modules, function($module) {
            return $module['has_access'] === true;
        }));
    }

    /**
     * Check if user can access a specific module in their current context
     */
    public function canAccessModule(int $userId, string $moduleCode, ?string $contextType = null, ?int $facilityId = null): bool
    {
        $context = $this->resolve($userId);
        
        // If no context type specified, check all capabilities
        if (!$contextType) {
            foreach ($context['module_access'] as $capabilityType => $capabilityData) {
                if ($capabilityType === 'staff_with_facilities' && $facilityId) {
                    $facilityAccess = $capabilityData[$facilityId] ?? null;
                    if ($facilityAccess) {
                        foreach ($facilityAccess['modules'] ?? [] as $module) {
                            if ($module['code'] === $moduleCode && $module['has_access']) {
                                return true;
                            }
                        }
                    }
                } else {
                    foreach ($capabilityData['modules'] ?? [] as $module) {
                        if ($module['code'] === $moduleCode && $module['has_access']) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }
        
        // Check specific context type
        if (!isset($context['module_access'][$contextType])) {
            return false;
        }
        
        if ($contextType === 'staff_with_facilities' && $facilityId) {
            $facilityAccess = $context['module_access'][$contextType][$facilityId] ?? null;
            if (!$facilityAccess) return false;
            
            foreach ($facilityAccess['modules'] ?? [] as $module) {
                if ($module['code'] === $moduleCode && $module['has_access']) {
                    return true;
                }
            }
        } else {
            foreach ($context['module_access'][$contextType]['modules'] ?? [] as $module) {
                if ($module['code'] === $moduleCode && $module['has_access']) {
                    return true;
                }
            }
        }
        
        return false;
    }
}