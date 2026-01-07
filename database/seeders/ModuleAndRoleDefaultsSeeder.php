<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\RoleModuleDefault;
use App\Models\FacilityRole;

class ModuleAndRoleDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        // 1️⃣ Fetch roles from FacilityRoleSeeder
        $roles = FacilityRole::all();

        foreach ($roles as $role) {
            // 2️⃣ Create module for each role
            Module::updateOrCreate(
                ['code' => $role->slug],
                ['name' => $role->name]
            );

            // 3️⃣ Create role-module default record
            RoleModuleDefault::updateOrCreate(
                [
                    'role_code'   => $role->slug, // role_code = role slug
                    'module_code' => $role->slug, // module_code = role slug
                ],
                [
                    'default_access' => true,
                ]
            );
        }
    }
}
