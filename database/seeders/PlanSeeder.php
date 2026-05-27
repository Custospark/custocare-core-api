<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\Billing\PlanFeatures;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * PlanSeeder
 *
 * Seeds the three Custocare facility subscription plans.
 * Feature flags mirror Custocare_Pricing_Strategy.ipynb §4.
 *
 * Run with: php artisan db:seed --class=PlanSeeder
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                   => 'Essential',
                'slug'                   => 'essential',
                'description'            => 'For independent clinics, labs, pharmacies and small practices. Full clinical workflows — order tests, prescribe, and manage patients from the visit screen.',
                'price_usd'              => 39.00,
                'price_ugx'              => 144_000.00,
                'onboarding_fee_usd'     => 0.00,
                'onboarding_fee_ugx'     => 0.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 14,
                'max_staff'              => 10,
                'max_departments'        => 3,
                'max_visits_per_month' => 500,
                'features'               => PlanFeatures::defaultFeatureFlagsForPlan('essential'),
                'sort_order'             => 1,
                'is_popular'             => false,
                'is_active'              => true,
            ],
            [
                'name'                   => 'Professional',
                'slug'                   => 'professional',
                'description'            => 'For growing hospitals and full-care facilities. Everything in Essential, plus dedicated departmental workspaces.',
                'price_usd'              => 149.00,
                'price_ugx'              => 550_000.00,
                'onboarding_fee_usd'     => 99.00,
                'onboarding_fee_ugx'     => 366_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 14,
                'max_staff'              => 50,
                'max_departments'        => 10,
                'max_visits_per_month' => 3000,
                'features'               => PlanFeatures::defaultFeatureFlagsForPlan('professional'),
                'sort_order'             => 2,
                'is_popular'             => true,
                'is_active'              => true,
            ],
            [
                'name'                   => 'Enterprise',
                'slug'                   => 'enterprise',
                'description'            => 'For large hospitals, groups, and health systems. Everything in Professional, plus logistics and network management.',
                'price_usd'              => 399.00,
                'price_ugx'              => 1_475_000.00,
                'onboarding_fee_usd'     => 249.00,
                'onboarding_fee_ugx'     => 920_000.00,
                'billing_cycle'          => 'monthly',
                'trial_days'             => 14,
                'max_staff'              => null,
                'max_departments'        => null,
                'max_visits_per_month' => null,
                'features'               => PlanFeatures::defaultFeatureFlagsForPlan('enterprise'),
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
