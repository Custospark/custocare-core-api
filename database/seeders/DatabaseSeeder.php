<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\RoleModuleDefault;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
   public function run(): void
    {
        // 1️⃣ Create modules
        $modules = [
            ['code' => 'my_appointments', 'name' => 'My Appointments'],
            ['code' => 'medical_records', 'name' => 'Medical Records'],
            ['code' => 'my_invitations', 'name' => 'My Invitations'],
            ['code' => 'my_schedule', 'name' => 'My Schedule'],
            ['code' => 'billing', 'name' => 'Billing'],
            ['code' => 'clinical', 'name' => 'Clinical'],
            ['code' => 'reports', 'name' => 'Reports'],
            ['code' => 'regulator_dashboard', 'name' => 'Regulator Dashboard'],
        ];

        foreach ($modules as $m) {
            Module::updateOrCreate(['code' => $m['code']], $m);
        }

        // 2️⃣ Assign role-based default access
        $roleDefaults = [
            // Patients
            ['role_code' => 'patient', 'module_code' => 'my_appointments', 'default_access' => true],
            ['role_code' => 'patient', 'module_code' => 'medical_records', 'default_access' => true],

            // Staff (without facility roles get these by default)
            ['role_code' => 'staff', 'module_code' => 'my_invitations', 'default_access' => true],
            ['role_code' => 'staff', 'module_code' => 'my_schedule', 'default_access' => true],

            ['role_code' => 'facility_administrator', 'module_code' => 'administration', 'default_access' => true],
            ['role_code' => 'facility_administrator', 'module_code' => 'patients', 'default_access' => true],
            ['role_code' => 'facility_administrator', 'module_code' => 'billing-finance', 'default_access' => true],
            // Super Admins
            ['role_code' => 'super_admin', 'module_code' => 'medical_records', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'my_invitations', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'my_schedule', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'billing', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'clinical', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'reports', 'default_access' => true],
            ['role_code' => 'super_admin', 'module_code' => 'regulator_dashboard', 'default_access' => true],

            // Regulators
            ['role_code' => 'regulator', 'module_code' => 'regulator_dashboard', 'default_access' => true],
            ['role_code' => 'regulator', 'module_code' => 'reports', 'default_access' => true],
        ];

        foreach ($roleDefaults as $rd) {
            RoleModuleDefault::updateOrCreate(
                [
                    'role_code' => $rd['role_code'],
                    'module_code' => $rd['module_code'],
                ],
                [
                    'default_access' => $rd['default_access'],
                ]
            );
        }
    }
}
