<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\Billing\PlanFeatures;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    public function test_default_essential_plan_excludes_professional_workspaces(): void
    {
        $features = PlanFeatures::defaultFeatureFlagsForPlan('essential');
        $enabled = PlanFeatures::enabledModuleCodes($features);

        $this->assertContains('medical_records', $enabled);
        $this->assertContains('custocare_hub', $enabled);
        $this->assertNotContains('clinical', $enabled);
        $this->assertNotContains('laboratory', $enabled);
        $this->assertNotContains('referrals', $enabled);
    }

    public function test_default_enterprise_plan_includes_referrals_and_ambulance(): void
    {
        $features = PlanFeatures::defaultFeatureFlagsForPlan('enterprise');
        $enabled = PlanFeatures::enabledModuleCodes($features);

        $this->assertContains('referrals', $enabled);
        $this->assertContains('ambulance', $enabled);
        $this->assertContains('clinical', $enabled);
    }

    public function test_owner_restricted_modules_include_administration(): void
    {
        $this->assertSame(
            ['account', 'custocare_hub', 'administration'],
            PlanFeatures::ownerRestrictedModuleCodes(),
        );
    }

    public function test_ensure_owner_administration_adds_administration_module(): void
    {
        $result = PlanFeatures::ensureOwnerAdministration(['medical_records', 'account']);

        $this->assertContains('administration', $result);
        $this->assertContains('medical_records', $result);
    }

    public function test_intersect_role_modules_with_plan_preserves_always_available_modules(): void
    {
        $result = PlanFeatures::intersectRoleModulesWithPlan(
            ['medical_records'],
            ['medical_records'],
        );

        $this->assertSame(['medical_records', 'account', 'custocare_hub', 'patient_dashboard'], $result);
    }

    public function test_is_restricted_only_module_set_detects_owner_and_staff_fallbacks(): void
    {
        $this->assertTrue(PlanFeatures::isRestrictedOnlyModuleSet(PlanFeatures::ownerRestrictedModuleCodes()));
        $this->assertTrue(PlanFeatures::isRestrictedOnlyModuleSet(PlanFeatures::restrictedModuleCodes()));
        $this->assertTrue(PlanFeatures::isRestrictedOnlyModuleSet([]));
        $this->assertFalse(PlanFeatures::isRestrictedOnlyModuleSet(['medical_records', 'account', 'custocare_hub']));
    }
}
