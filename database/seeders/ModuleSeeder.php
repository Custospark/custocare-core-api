<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'code' => 'clinical',
                'name' => 'Clinical',
                'description' => 'Module for medical doctors to manage clinical workflows and patient care.',
                'is_active' => true,
            ],
            [
                'code' => 'pharmacy',
                'name' => 'Pharmacy',
                'description' => 'Module for pharmacists to manage prescriptions and pharmaceutical inventory.',
                'is_active' => true,
            ],
            [
                'code' => 'nursing',
                'name' => 'Nursing',
                'description' => 'Module for registered nurses to monitor patients, assist in procedures, and manage nursing care.',
                'is_active' => true,
            ],
            [
                'code' => 'reception',
                'name' => 'Reception',
                'description' => 'Module for receptionists to handle patient check-ins, appointments, and front desk operations.',
                'is_active' => true,
            ],
            [
                'code' => 'administration',
                'name' => 'Administration',
                'description' => 'Module for facility administrators to oversee operations, staff, and workflows.',
                'is_active' => true,
            ],
            [
                'code' => 'laboratory',
                'name' => 'Laboratory',
                'description' => 'Module for laboratory scientists to manage lab tests, results, and reporting.',
                'is_active' => true,
            ],
            [
                'code' => 'billing',
                'name' => 'Billing',
                'description' => 'Module for billing officers to manage invoices, insurance claims, and financial records.',
                'is_active' => true,
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['code' => $module['code']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'is_active' => $module['is_active'],
                ]
            );
        }
    }
}
