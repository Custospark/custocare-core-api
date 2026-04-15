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
            'profile_photo_path' => $user->profile_photo_path,
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
                'patient_uuid' => $patient->patient_uuid,
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
                'staff_uuid' => $staff->staff_uuid,
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

        // Ensure account module is accessible in ALL capabilities
        $capabilities = $this->ensureAccountAccessInAllCapabilities($capabilities, $allModules);

        return $capabilities;
    }

    /**
     * Ensure account module is accessible in every capability
     * Handles both direct modules array and facility modules
     */
    protected function ensureAccountAccessInAllCapabilities(array $capabilities, Collection $allModules): array
    {
        foreach ($capabilities as $capabilityName => &$capabilityData) {
            // Handle staff capability with facilities
            if ($capabilityName === 'staff' && isset($capabilityData['facilities']) && !empty($capabilityData['facilities'])) {
                // Ensure account module is in EACH facility's modules
                foreach ($capabilityData['facilities'] as &$facility) {
                    if (!isset($facility['modules'])) {
                        continue;
                    }
                    
                    $accountFound = false;
                    foreach ($facility['modules'] as &$module) {
                        if ($module['code'] === 'account') {
                            $module['is_active'] = true;
                            $accountFound = true;
                            break;
                        }
                    }
                    
                    if (!$accountFound && $allModules->has('account')) {
                        $accountModule = $allModules->get('account');
                        $facility['modules'][] = [
                            'id' => $accountModule->id,
                            'code' => $accountModule->code,
                            'name' => $accountModule->name,
                            'description' => $accountModule->description,
                            'is_active' => true,
                        ];
                    }
                }
                continue;
            }
            
            // Handle capabilities with direct modules array
            if (!isset($capabilityData['modules'])) {
                continue;
            }

            $accountFound = false;
            foreach ($capabilityData['modules'] as &$module) {
                if ($module['code'] === 'account') {
                    $module['is_active'] = true;
                    $accountFound = true;
                    break;
                }
            }

            if (!$accountFound && $allModules->has('account')) {
                $accountModule = $allModules->get('account');
                $capabilityData['modules'][] = [
                    'id' => $accountModule->id,
                    'code' => $accountModule->code,
                    'name' => $accountModule->name,
                    'description' => $accountModule->description,
                    'is_active' => true,
                ];
            }
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
            ->pluck('module_code')
            ->first();
        
        $accessibleCodes = is_array($patientAccess) ? $patientAccess : (json_decode($patientAccess ?? '[]', true) ?? []);
        
        if (!in_array('account', $accessibleCodes)) {
            $accessibleCodes[] = 'account';
        }

        return $this->buildModuleList($allModules, $accessibleCodes);
    }

    /**
     * Resolve staff modules from role_module_defaults (staff without facilities)
     */
    protected function resolveStaffModules(Collection $allModules): array
    {
        $staffAccess = RoleModuleDefault::where('role_code', 'staff')
            ->where('default_access', true)
            ->pluck('module_code')
            ->first();
        
        $accessibleCodes = is_array($staffAccess) ? $staffAccess : (json_decode($staffAccess ?? '[]', true) ?? []);
        
        if (!in_array('account', $accessibleCodes)) {
            $accessibleCodes[] = 'account';
        }

        return $this->buildModuleList($allModules, $accessibleCodes);
    }

    /**
     * Resolve Spatie role modules from role_module_defaults
     */
    protected function resolveSpatieRoleModules(string $roleName, Collection $allModules): array
    {
        $roleDefault = RoleModuleDefault::where('role_code', $roleName)
            ->where('default_access', true)
            ->first();
        
        $accessibleCodes = [];
        
        if ($roleDefault) {
            $moduleCodes = $roleDefault->module_code;
            
            if (is_string($moduleCodes)) {
                $accessibleCodes = json_decode($moduleCodes, true) ?? [];
            } elseif (is_array($moduleCodes)) {
                $accessibleCodes = $moduleCodes;
            }
            
            $accessibleCodes = array_values(array_filter($accessibleCodes, function($code) {
                return is_string($code) && !empty($code);
            }));
        }
        
        if (!in_array('account', $accessibleCodes)) {
            $accessibleCodes[] = 'account';
        }

        return $this->buildModuleList($allModules, $accessibleCodes);
    }

    /**
     * Resolve staff facilities with their modules
     * Filters out suspended/banned facilities and ensures account module is active
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
            $facility = $role->facility;
            
            // Skip suspended or banned facilities
            if (!$facility || in_array($facility->status, ['suspended', 'banned'])) {
                continue;
            }
            
            $moduleCodes = $this->extractModuleCodes($role->module_code);
            
            // Ensure account is always in the module codes for staff facilities
            if (!in_array('account', $moduleCodes)) {
                $moduleCodes[] = 'account';
            }
            
            $facilities[] = [
                'facility_id' => $role->facility_id,
                'facility_name' => $facility->facility_name ?? null,
                'facility_code' => $facility->facility_code ?? null,
                'legal_entity_name' => $facility->legal_entity_name ?? null,
                'health_system_name' => $facility->health_system_name ?? null,
                'nature_of_facility' => $facility->nature_of_facility ?? null,
                'facility_type' => $facility->facility_type ?? null,
                'facility_tier' => $facility->facility_tier ?? null,
                'bed_capacity' => $facility->bed_capacity ?? null,
                'available_services' => $facility->available_services ?? [],
                'specialty_services' => $facility->specialty_services ?? [],
                'equipment_inventory_summary' => $facility->equipment_inventory_summary ?? [],
                'address_line1' => $facility->address_line1 ?? null,
                'address_line2' => $facility->address_line2 ?? null,
                'city' => $facility->city ?? null,
                'state_province' => $facility->state_province ?? null,
                'postal_code' => $facility->postal_code ?? null,
                'country_code' => $facility->country_code ?? null,
                'latitude' => $facility->latitude ?? null,
                'longitude' => $facility->longitude ?? null,
                'main_phone' => $facility->main_phone ?? null,
                'emergency_phone' => $facility->emergency_phone ?? null,
                'fax' => $facility->fax ?? null,
                'email' => $facility->email ?? null,
                'website' => $facility->website ?? null,
                'operating_hours' => $facility->operating_hours ?? [],
                'emergency_services_hours' => $facility->emergency_services_hours ?? [],
                'is_24_7' => $facility->is_24_7 ?? false,
                'operational_status' => $facility->operational_status ?? null,
                'average_wait_time_minutes' => $facility->average_wait_time_minutes ?? null,
                'monthly_patient_volume' => $facility->monthly_patient_volume ?? null,
                'license_number' => $facility->license_number ?? null,
                'license_issuing_authority' => $facility->license_issuing_authority ?? null,
                'license_expiry_date' => $facility->license_expiry_date ?? null,
                'regulatory_identifiers' => $facility->regulatory_identifiers ?? [],
                'participates_in_medicare' => $facility->participates_in_medicare ?? false,
                'participates_in_medicaid' => $facility->participates_in_medicaid ?? false,
                'has_emergency_department' => $facility->has_emergency_department ?? false,
                'has_trauma_center' => $facility->has_trauma_center ?? false,
                'trauma_center_level' => $facility->trauma_center_level ?? null,
                'has_intensive_care' => $facility->has_intensive_care ?? false,
                'has_neonatal_icu' => $facility->has_neonatal_icu ?? false,
                'has_cardiac_cath_lab' => $facility->has_cardiac_cath_lab ?? false,
                'facility_currency' => $facility->currency ?? null,
                'tax_enabled' => $facility->tax_enabled ?? false,
                'tax_name' => $facility->tax_name ?? null,
                'tax_rate' => $facility->tax_rate ?? null,
                'facility_logo_path' => $facility->facility_logo_path ? asset('storage/' . $facility->facility_logo_path) : null,
                'primary_brand_color' => $facility->primary_brand_color ?? null,
                'secondary_brand_color' => $facility->secondary_brand_color ?? null,
                'timezone' => $facility->timezone ?? null,
                'data_residency_region' => $facility->data_residency_region ?? null,
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
     * Filters out suspended/banned facilities
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

        return $roles->filter(function ($role) {
            $facility = $role->facility;
            return $facility && !in_array($facility->status, ['suspended', 'banned']);
        })->map(function ($role) {
            return [
                'facility_id' => $role->facility_id,
                'facility_name' => $role->facility->facility_name ?? null,
                'facility_code' => $role->facility->facility_code ?? null,
                'role_code' => $role->role_code,
                'is_primary_facility' => $role->is_primary_facility,
            ];
        })->values()->toArray();
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
        if ($moduleCode === 'account') {
            return true;
        }

        $context = $this->resolve($userId);
        
        if (!isset($context['capabilities'][$capability])) {
            return false;
        }

        $capabilityData = $context['capabilities'][$capability];

        if ($capability === 'staff' && isset($capabilityData['facilities'])) {
            if ($facilityId === null) {
                $modules = $capabilityData['modules'] ?? [];
            } else {
                $facility = collect($capabilityData['facilities'])
                    ->firstWhere('facility_id', $facilityId);
                
                if (!$facility) return false;
                
                $modules = $facility['modules'] ?? [];
            }
        } else {
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
                $modules = $capabilityData['modules'] ?? [];
            } else {
                $facility = collect($capabilityData['facilities'])
                    ->firstWhere('facility_id', $facilityId);
                
                if (!$facility) return [];
                
                $modules = $facility['modules'] ?? [];
            }
        } else {
            $modules = $capabilityData['modules'] ?? [];
        }

        return array_values(array_filter($modules, function ($module) {
            return ($module['is_active'] ?? false) === true;
        }));
    }
}