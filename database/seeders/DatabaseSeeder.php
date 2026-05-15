<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\Module;
use App\Models\FacilityRole;
use App\Models\RoleModuleDefault;
use Spatie\Permission\Models\Role; // Add this

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Seed Modules (SYSTEM SOURCE OF TRUTH)
        |--------------------------------------------------------------------------
        */
        $modules = [
            ['code' => 'clinical', 'name' => 'Clinical', 'description' => 'Clinical workflows and patient care', 'is_active' => true],
            ['code' => 'pharmacy', 'name' => 'Pharmacy', 'description' => 'Prescriptions and pharmaceutical inventory', 'is_active' => true],
            ['code' => 'nursing', 'name' => 'Nursing', 'description' => 'Nursing care and patient monitoring', 'is_active' => true],
            ['code' => 'medical_records', 'name' => 'Medical Records', 'description' => 'Medical Records, Patient Registration & workflows', 'is_active' => true],
            ['code' => 'administration', 'name' => 'Administration', 'description' => 'Facility administration and management', 'is_active' => true],
            ['code' => 'laboratory', 'name' => 'Laboratory', 'description' => 'Lab tests and diagnostics', 'is_active' => true],
            ['code' => 'billing', 'name' => 'Billing', 'description' => 'Billing, invoices, and insurance', 'is_active' => true],
            ['code' => 'account','name' => 'Account','description' => 'Manage profile, security, invitations, messages, and preferences','is_active' => true],
            // ADD NEW PLATFORM ADMINISTRATION MODULE
            ['code' => 'platform_administration', 'name' => 'Platform Administration', 'description' => 'Global platform settings, system configuration, user management across all facilities', 'is_active' => true],
            ['code' => 'ambulance', 'name' => 'Ambulance Services', 'description' => 'Fleet management, dispatch, and trip tracking', 'is_active' => true],
            ['code' => 'referrals', 'name' => 'Referrals', 'description' => 'Patient referrals between facilities and providers', 'is_active' => true],
        ];

        foreach ($modules as $module) {
            if (empty($module['code'])) {
                Log::error('Module code missing', $module);
                continue;
            }

            Module::updateOrCreate(
                ['code' => $module['code']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'is_active' => $module['is_active'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Seed Facility Roles
        |--------------------------------------------------------------------------
        */
        $roles = [
            ['name' => 'Medical Doctor', 'code' => 'medical-doctor', 'description' => 'Clinical diagnosis and treatment', 'is_system_role' => true],
            ['name' => 'Pharmacist', 'code' => 'pharmacist', 'description' => 'Medication dispensing and review', 'is_system_role' => true],
            ['name' => 'Registered Nurse', 'code' => 'registered-nurse', 'description' => 'Nursing care and support', 'is_system_role' => true],
            ['name' => 'Receptionist', 'code' => 'receptionist', 'description' => 'Front desk operations', 'is_system_role' => true],
            ['name' => 'Facility Administrator', 'code' => 'facility-administrator', 'description' => 'Facility operations and oversight', 'is_system_role' => true],
            ['name' => 'Laboratory Scientist', 'code' => 'laboratory-scientist', 'description' => 'Laboratory diagnostics', 'is_system_role' => true],
            ['name' => 'Billing Officer', 'code' => 'billing-officer', 'description' => 'Billing and financial management', 'is_system_role' => true],
        ];

        foreach ($roles as $role) {
            if (empty($role['code'])) {
                Log::error('Role code missing', $role);
                continue;
            }

            FacilityRole::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Create Spatie Roles for BOTH guards
        |--------------------------------------------------------------------------
        */
        // Create super_admin role for web guard
        Role::firstOrCreate([
            'name' => 'super_admin', 
            'guard_name' => 'web'
        ]);

        // Create super_admin role for api guard
        Role::firstOrCreate([
            'name' => 'super_admin', 
            'guard_name' => 'api'
        ]);
        
        // You can add other global Spatie roles here if needed
        // Role::firstOrCreate(['name' => 'global_viewer', 'guard_name' => 'web']);

        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Facility Role → Module Defaults (JSON-BASED, STRICT)
        |--------------------------------------------------------------------------
        | One row per facility role
        | module_code = JSON ARRAY
        |--------------------------------------------------------------------------
        */
        $roleToModuleMap = [
            // Clinician-focused defaults: each role lands in its core workspace.
            'medical-doctor' => ['account', 'clinical', 'referrals'],
            'pharmacist' => ['account', 'pharmacy'],
            'registered-nurse' => ['account', 'nursing', 'referrals'],
            'laboratory-scientist' => ['account', 'laboratory'],
            'billing-officer' => ['account', 'billing'],

            // Front-desk and admin roles keep broad operational access.
            'receptionist' => ['account', 'medical_records'],
            'facility-administrator' => ['account','clinical','pharmacy','nursing','medical_records','laboratory','billing','administration','ambulance','referrals'],
        ];

        foreach ($roleToModuleMap as $roleCode => $moduleCodes) {
            if (!FacilityRole::where('code', $roleCode)->exists()) {
                Log::warning("⚠️ Facility role not found: {$roleCode}");
                continue;
            }

            // Validate modules exist
            $validModules = Module::whereIn('code', $moduleCodes)
                ->pluck('code')
                ->values()
                ->toArray();

            if (empty($validModules)) {
                Log::warning("⚠️ No valid modules for facility role: {$roleCode}");
                continue;
            }

            RoleModuleDefault::updateOrCreate(
                ['role_code' => $roleCode],
                [
                    'module_code' => $validModules, // JSON
                    'default_access' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5️⃣ Spatie Role → Module Defaults (FOR GLOBAL ROLES)
        |--------------------------------------------------------------------------
        | One row per Spatie role
        | module_code = JSON ARRAY
        |--------------------------------------------------------------------------
        */
        $spatieRoleToModuleMap = [
            'super_admin' => ['account', 'platform_administration', 'ambulance', 'referrals'], // Super admin gets full access
            // Add other Spatie roles here if needed
            // 'global_viewer' => ['account', 'clinical_readonly'],
        ];

        foreach ($spatieRoleToModuleMap as $roleName => $moduleCodes) {
            // Verify Spatie role exists
            if (!Role::where('name', $roleName)->whereIn('guard_name', ['web','api'])->exists()) {
                Log::warning("⚠️ Spatie role not found: {$roleName}");
                continue;
            }

            // Validate modules exist
            $validModules = Module::whereIn('code', $moduleCodes)
                ->where('is_active', true)
                ->pluck('code')
                ->values()
                ->toArray();

            if (empty($validModules)) {
                Log::warning("⚠️ No valid modules for Spatie role: {$roleName}");
                continue;
            }

            RoleModuleDefault::updateOrCreate(
                ['role_code' => $roleName], // Same table, role_code stores Spatie role name
                [
                    'module_code' => $validModules, // JSON
                    'default_access' => true,
                ]
            );
        }

        $this->command->info('✅ Database seeded: Modules, Facility Roles, Spatie Roles, and RoleModuleDefaults successfully.');
    }
}