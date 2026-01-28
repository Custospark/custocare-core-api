<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\Module;
use App\Models\FacilityRole;
use App\Models\RoleModuleDefault;

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
            ['code' => 'medical_records', 'name' => 'Medical Records', 'description' => 'Registration & Medical Records', 'is_active' => true],
            ['code' => 'administration', 'name' => 'Administration', 'description' => 'Facility administration and management', 'is_active' => true],
            ['code' => 'laboratory', 'name' => 'Laboratory', 'description' => 'Lab tests and diagnostics', 'is_active' => true],
            ['code' => 'billing', 'name' => 'Billing', 'description' => 'Billing, invoices, and insurance', 'is_active' => true],
            ['code' => 'account','name' => 'Account','description' => 'Manage profile, security, invitations, messages, and preferences','is_active' => true,
            ],
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
        | 3️⃣ Role → Module Defaults (JSON-BASED, STRICT)
        |--------------------------------------------------------------------------
        | One row per role
        | module_code = JSON ARRAY
        |--------------------------------------------------------------------------
        */
        $roleToModuleMap = [
            'medical-doctor' => ['account'],
            'pharmacist' => ['account'],
            'registered-nurse' => ['account'],
            'receptionist' => ['account'],
            'facility-administrator' => ['account','clinical','pharmacy','nursing','medical_records','laboratory','billing','administration'],
            'laboratory-scientist' => ['account'],
            'billing-officer' => ['account'],
        ];

        foreach ($roleToModuleMap as $roleCode => $moduleCodes) {

            if (!FacilityRole::where('code', $roleCode)->exists()) {
                Log::warning("⚠️ Role not found: {$roleCode}");
                continue;
            }

            // Validate modules exist
            $validModules = Module::whereIn('code', $moduleCodes)
                ->pluck('code')
                ->values()
                ->toArray();

            if (empty($validModules)) {
                Log::warning("⚠️ No valid modules for role: {$roleCode}");
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

        $this->command->info('✅ Database seeded: Modules, Roles, and RoleModuleDefaults successfully.');
    }
}
