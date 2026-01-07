<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FacilityRole;
use App\Models\Module;
use App\Models\RoleModuleDefault;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------
        // 1️⃣ Seed Modules
        // -----------------------------
        $modules = [
            ['code' => 'clinical', 'name' => 'Clinical', 'description' => 'Module for medical doctors to manage clinical workflows and patient care.', 'is_active' => true],
            ['code' => 'pharmacy', 'name' => 'Pharmacy', 'description' => 'Module for pharmacists to manage prescriptions and pharmaceutical inventory.', 'is_active' => true],
            ['code' => 'nursing', 'name' => 'Nursing', 'description' => 'Module for registered nurses to monitor patients, assist in procedures, and manage nursing care.', 'is_active' => true],
            ['code' => 'reception', 'name' => 'Reception', 'description' => 'Module for receptionists to handle patient check-ins, appointments, and front desk operations.', 'is_active' => true],
            ['code' => 'administration', 'name' => 'Administration', 'description' => 'Module for facility administrators to oversee operations, staff, and workflows.', 'is_active' => true],
            ['code' => 'laboratory', 'name' => 'Laboratory', 'description' => 'Module for laboratory scientists to manage lab tests, results, and reporting.', 'is_active' => true],
            ['code' => 'billing', 'name' => 'Billing', 'description' => 'Module for billing officers to manage invoices, insurance claims, and financial records.', 'is_active' => true],
        ];

        foreach ($modules as $module) {
            if (empty($module['code'])) {
                Log::error('Module code is empty!', $module);
                continue;
            }

            Module::updateOrCreate(
                ['code' => $module['code']],
                [
                    'name' => $module['name'] ?? 'Unnamed Module',
                    'description' => $module['description'] ?? null,
                    'is_active' => $module['is_active'] ?? true,
                ]
            );
        }

        // -----------------------------
        // 2️⃣ Seed Facility Roles
        // -----------------------------
        $roles = [
            [
                'name' => 'Medical Doctor',
                'code' => 'medical-doctor',
                'description' => 'Provides clinical diagnosis, treatment, and medical decision-making for patients.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Pharmacist',
                'code' => 'pharmacist',
                'description' => 'Dispenses medications, reviews prescriptions, and ensures safe pharmaceutical care.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Registered Nurse',
                'code' => 'registered-nurse',
                'description' => 'Delivers nursing care, monitors patient conditions, and supports clinical procedures.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Receptionist',
                'code' => 'receptionist',
                'description' => 'Manages patient reception, appointment scheduling, and front-desk communication.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Facility Administrator',
                'code' => 'facility-administrator',
                'description' => 'Oversees facility operations, staff coordination, and administrative workflows.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Laboratory Scientist',
                'code' => 'laboratory-scientist',
                'description' => 'Conducts laboratory tests, analyzes specimens, and reports diagnostic results.',
                'is_system_role' => true,
            ],
            [
                'name' => 'Billing Officer',
                'code' => 'billing-officer',
                'description' => 'Manages patient billing, invoices, insurance claims, and financial records.',
                'is_system_role' => true,
            ],
        ];

        foreach ($roles as $role) {
            if (empty($role['code'])) {
                Log::error('Role code is empty!', $role);
                continue;
            }

            FacilityRole::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }

        // -----------------------------
        // 3️⃣ Create Role-Module Defaults
        // -----------------------------
                $roleToModuleMap = [
                'medical-doctor'         => 'clinical',
                'pharmacist'             => 'pharmacy',
                'registered-nurse'       => 'nursing',
                'receptionist'           => 'reception',
                'facility-administrator' => 'administration',
                'laboratory-scientist'   => 'laboratory',
                'billing-officer'        => 'billing',
            ];

            foreach ($roleToModuleMap as $roleCode => $moduleCode) {
                $role = FacilityRole::where('code', $roleCode)->first();
                $module = Module::where('code', $moduleCode)->first();

                if ($role && $module) {
                    RoleModuleDefault::updateOrCreate(
                        [
                            'role_code' => $role->code,
                            'module_code' => $module->code,
                        ],
                        [
                            'default_access' => true,
                        ]
                    );
                }
            }

        $this->command->info('✅ InitialSetupSeeder: Modules, Roles, and Role-Module defaults created successfully.');
    }
}
