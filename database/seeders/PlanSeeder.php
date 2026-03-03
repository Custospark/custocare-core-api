<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * PlanSeeder
 *
 * Seeds the three Custocare facility subscription plans.
 * Run with: php artisan db:seed --class=PlanSeeder
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // ── Essential ─────────────────────────────────────────────────
            [
                'name'                   => 'Essential',
                'slug'                   => 'essential',
                'description'            => 'Perfect for small clinics and primary care facilities getting started with Custocare.',
                'price_usd'              => 65.00,
                'price_ugx'              => 240_000.00,
                'onboarding_fee_usd'     => 50.00,
                'onboarding_fee_ugx'     => 185_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => 10,
                'max_departments'        => 3,
                'max_patients_per_month' => 500,
                'features'               => [
                    'patient_records'         => true,
                    'appointments'            => true,
                    'basic_billing'           => true,
                    'lab_integration'         => false,
                    'pharmacy_module'         => false,
                    'advanced_analytics'      => false,
                    'api_access'              => false,
                    'priority_support'        => false,
                    'custom_branding'         => false,
                    'multi_branch'            => false,
                ],
                'sort_order'             => 1,
                'is_popular'             => false,
                'is_active'              => true,
            ],

            // ── Professional ──────────────────────────────────────────────
            [
                'name'                   => 'Professional',
                'slug'                   => 'professional',
                'description'            => 'For growing healthcare facilities that need advanced modules and integrations.',
                'price_usd'              => 120.00,
                'price_ugx'              => 440_000.00,
                'onboarding_fee_usd'     => 100.00,
                'onboarding_fee_ugx'     => 370_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => 50,
                'max_departments'        => 10,
                'max_patients_per_month' => 2000,
                'features'               => [
                    'patient_records'         => true,
                    'appointments'            => true,
                    'basic_billing'           => true,
                    'lab_integration'         => true,
                    'pharmacy_module'         => true,
                    'advanced_analytics'      => false,
                    'api_access'              => true,
                    'priority_support'        => false,
                    'custom_branding'         => true,
                    'multi_branch'            => false,
                ],
                'sort_order'             => 2,
                'is_popular'             => true,   // Highlighted on pricing page
                'is_active'              => true,
            ],

            // ── Enterprise ────────────────────────────────────────────────
            [
                'name'                   => 'Enterprise',
                'slug'                   => 'enterprise',
                'description'            => 'For hospitals and multi-branch networks requiring unlimited capacity and dedicated support.',
                'price_usd'              => 250.00,
                'price_ugx'              => 925_000.00,
                'onboarding_fee_usd'     => 200.00,
                'onboarding_fee_ugx'     => 740_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => null,    // Unlimited
                'max_departments'        => null,    // Unlimited
                'max_patients_per_month' => null,    // Unlimited
                'features'               => [
                    'patient_records'         => true,
                    'appointments'            => true,
                    'basic_billing'           => true,
                    'lab_integration'         => true,
                    'pharmacy_module'         => true,
                    'advanced_analytics'      => true,
                    'api_access'              => true,
                    'priority_support'        => true,
                    'custom_branding'         => true,
                    'multi_branch'            => true,
                ],
                'sort_order'             => 3,
                'is_popular'             => false,
                'is_active'              => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('✅ Custocare plans seeded: Essential, Professional, Enterprise');
    }
}
