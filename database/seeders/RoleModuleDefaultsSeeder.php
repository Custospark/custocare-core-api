<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FacilityRole;
use App\Models\Module;
use App\Models\RoleModuleDefault;

class RoleModuleDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 1️⃣ Fetch all roles and modules
         */
        $roles = FacilityRole::all();
        $modules = Module::all()->keyBy('code'); // key by code for easy lookup

        /**
         * 2️⃣ Define role → module mapping
         * Each role gets its corresponding primary module.
         */
        $roleToModuleMap = [
            'medical-doctor'       => 'clinical',
            'pharmacist'           => 'pharmacy',
            'registered-nurse'     => 'nursing',
            'receptionist'         => 'reception',
            'facility-administrator' => 'administration',
            'laboratory-scientist' => 'laboratory',
            'billing-officer'      => 'billing',
        ];

        foreach ($roleToModuleMap as $roleCode => $moduleCode) {
            // ensure both role and module exist
            if ($roles->pluck('code')->contains($roleCode) && $modules->has($moduleCode)) {
                RoleModuleDefault::updateOrCreate(
                    [
                        'role_code' => $roleCode,
                        'module_code' => $moduleCode,
                    ],
                    [
                        'default_access' => true,
                    ]
                );
            }
        }
    }
}
