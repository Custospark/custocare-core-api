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

         $this->call([
            FacilityRoleSeeder::class,
            ModuleAndRoleDefaultsSeeder::class,
        ]);
    }
}
