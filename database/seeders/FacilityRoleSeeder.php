<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FacilityRole;

class FacilityRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Medical Doctor',
                'code' => 'medical-doctor', // unique identifier
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
            FacilityRole::updateOrCreate(
                ['code' => $role['code']], // ensure uniqueness
                $role
            );
        }
    }
}
