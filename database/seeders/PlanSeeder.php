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
                'description'            => 'For independent clinics,labs,pharmacies and small practices. Full clinical workflows — order tests, prescribe, and manage patients from the visit screen.',
                'price_usd'              => 39.00,
                'price_ugx'              => 144_000.00,
                'onboarding_fee_usd'     => 0.00,
                'onboarding_fee_ugx'     => 0.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => 10,
                'max_departments'        => 3,
                'max_patients_per_month' => 500,
                'features'               => [
                    'patient_dashboard'       => true,
                    'medical_records'         => true,
                    'nursing'                 => false,
                    'clinical'                => false,
                    'laboratory'              => false,
                    'pharmacy'                => false,
                    'billing'                 => true,
                    'administration'          => true,
                    'platform_administration' => false,
                    'account'                 => true,
                    'messaging_center'        => true,
                    'api_access'              => false,
                    'analytics_dashboards'    => false,
                    'audit_logs'              => false,
                ],
                'sort_order'             => 1,
                'is_popular'             => false,
                'is_active'              => true,
            ],

            // ── Professional ──────────────────────────────────────────────
            [
                'name'                   => 'Professional',
                'slug'                   => 'professional',
                'description'            => 'For growing hospitals and full-care facilities. Everything in Essential, plus dedicated departmental workspaces.',
                'price_usd'              => 149.00,
                'price_ugx'              => 550_000.00,
                'onboarding_fee_usd'     => 99.00,
                'onboarding_fee_ugx'     => 366_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => 50,
                'max_departments'        => 10,
                'max_patients_per_month' => 3000,
                'features'               => [
                    'patient_dashboard'       => true,
                    'medical_records'         => true,
                    'nursing'                 => true,
                    'clinical'                => true,
                    'laboratory'              => true,
                    'pharmacy'                => true,
                    'billing'                 => true,
                    'administration'          => true,
                    'platform_administration' => false,
                    'account'                 => true,
                    'messaging_center'        => true,
                    'api_access'              => true,
                    'analytics_dashboards'    => true,
                    'audit_logs'              => true,
                ],
                'sort_order'             => 2,
                'is_popular'             => true,
                'is_active'              => true,
            ],

            // ── Enterprise ────────────────────────────────────────────────
            [
                'name'                   => 'Enterprise',
                'slug'                   => 'enterprise',
                'description'            => 'For large hospitals, groups, and health systems. Everything in Professional, plus logistics and network management.',
                'price_usd'              => 399.00,
                'price_ugx'              => 1_475_000.00,
                'onboarding_fee_usd'     => 249.00,
                'onboarding_fee_ugx'     => 920_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 7,
                'max_staff'              => null,
                'max_departments'        => null,
                'max_patients_per_month' => null,
                'features'               => [
                    'patient_dashboard'       => true,
                    'medical_records'         => true,
                    'nursing'                 => true,
                    'clinical'                => true,
                    'laboratory'              => true,
                    'pharmacy'                => true,
                    'billing'                 => true,
                    'administration'          => true,
                    'platform_administration' => true,
                    'account'                 => true,
                    'messaging_center'        => true,
                    'api_access'              => true,
                    'analytics_dashboards'    => true,
                    'audit_logs'              => true,
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
