<?php

declare(strict_types=1);

namespace App\Constants\Billing;

/**
 * Canonical plan feature keys and module-tier mapping.
 *
 * Source of truth: Frontend/Custocare_Pricing_Strategy.ipynb §4 Technical Module Mapping.
 * Module codes align with the `modules.code` column and Frontend Sidebar `moduleCode` values.
 */
final class PlanFeatures
{
    /**
     * Module codes that can be gated by facility subscription plans.
     *
     * @var list<string>
     */
    public const MODULE_CODES = [
        'medical_records',
        'administration',
        'billing',
        'account',
        'patient_dashboard',
        'custocare_hub',
        'nursing',
        'clinical',
        'laboratory',
        'pharmacy',
        'referrals',
        'ambulance',
    ];

    /**
     * Modules available regardless of plan tier (per pricing strategy §4 notes).
     *
     * @var list<string>
     */
    public const ALWAYS_AVAILABLE_MODULES = [
        'account',
        'custocare_hub',
    ];

    /**
     * Non-module capability flags stored in plan.features JSON.
     *
     * @var list<string>
     */
    public const ADDON_FLAGS = [
        'messaging_center',
        'api_access',
        'analytics_dashboards',
        'audit_logs',
    ];

    /**
     * All valid keys for plan.features JSON validation.
     * platform_administration is intentionally excluded — Spatie super_admin only.
     *
     * @var list<string>
     */
    public const ALL = [
        ...self::MODULE_CODES,
        ...self::ADDON_FLAGS,
    ];

    /**
     * Default plan.features payload per plan slug.
     *
     * @return array<string, bool>
     */
    public static function defaultFeatureFlagsForPlan(string $slug): array
    {
        $essentialModules = [
            'medical_records'   => true,
            'administration'    => true,
            'billing'           => true,
            'account'           => true,
            'patient_dashboard' => true,
            'custocare_hub'     => true,
            'nursing'           => false,
            'clinical'          => false,
            'laboratory'        => false,
            'pharmacy'          => false,
            'referrals'         => false,
            'ambulance'         => false,
        ];

        $essentialAddons = [
            'messaging_center'     => true,
            'api_access'           => false,
            'analytics_dashboards' => false,
            'audit_logs'           => false,
        ];

        $professionalAddons = [
            'messaging_center'     => true,
            'api_access'           => true,
            'analytics_dashboards' => true,
            'audit_logs'           => true,
        ];

        return match ($slug) {
            'essential' => array_merge($essentialModules, $essentialAddons),
            'professional' => array_merge($essentialModules, [
                'nursing'    => true,
                'clinical'   => true,
                'laboratory' => true,
                'pharmacy'   => true,
            ], $professionalAddons),
            'enterprise' => array_merge($essentialModules, [
                'nursing'    => true,
                'clinical'   => true,
                'laboratory' => true,
                'pharmacy'   => true,
                'referrals'  => true,
                'ambulance'  => true,
            ], $professionalAddons),
            default => array_merge(
                array_fill_keys(self::MODULE_CODES, false),
                array_fill_keys(self::ADDON_FLAGS, false),
                ['account' => true, 'custocare_hub' => true],
            ),
        };
    }

    /**
     * Resolve module codes enabled by a plan's features JSON.
     *
     * @param  array<string, mixed>|null  $features
     * @return list<string>
     */
    public static function enabledModuleCodes(?array $features): array
    {
        if ($features === null || $features === []) {
            return self::ALWAYS_AVAILABLE_MODULES;
        }

        $enabled = [];
        foreach (self::MODULE_CODES as $code) {
            if (! empty($features[$code])) {
                $enabled[] = $code;
            }
        }

        return array_values(array_unique(array_merge($enabled, self::ALWAYS_AVAILABLE_MODULES)));
    }

    /**
     * Intersect role-assigned modules with plan-enabled modules, preserving always-available modules.
     *
     * @param  list<string>  $roleModuleCodes
     * @param  list<string>  $planModuleCodes
     * @return list<string>
     */
    public static function intersectRoleModulesWithPlan(array $roleModuleCodes, array $planModuleCodes): array
    {
        $intersected = array_values(array_intersect($roleModuleCodes, $planModuleCodes));

        foreach (self::ALWAYS_AVAILABLE_MODULES as $code) {
            if (! in_array($code, $intersected, true)) {
                $intersected[] = $code;
            }
        }

        return $intersected;
    }

    /**
     * Minimum modules when facility is restricted or subscription is inaccessible.
     *
     * @return list<string>
     */
    public static function restrictedModuleCodes(): array
    {
        return self::ALWAYS_AVAILABLE_MODULES;
    }

    /**
     * Modules for facility owners when subscription is inactive or facility is restricted.
     * Includes administration so owners can manage billing and subscriptions.
     *
     * @return list<string>
     */
    public static function ownerRestrictedModuleCodes(): array
    {
        return ['account', 'custocare_hub', 'administration'];
    }

    /**
     * Ensure facility owners retain administration workspace access.
     *
     * @param  list<string>  $moduleCodes
     * @return list<string>
     */
    public static function ensureOwnerAdministration(array $moduleCodes): array
    {
        if (! in_array('administration', $moduleCodes, true)) {
            $moduleCodes[] = 'administration';
        }

        return array_values(array_unique($moduleCodes));
    }

    /**
     * True when persisted assignment modules are only the inactive-subscription fallback set.
     *
     * @param  list<string>  $moduleCodes
     */
    public static function isRestrictedOnlyModuleSet(array $moduleCodes): bool
    {
        $normalized = array_values(array_unique($moduleCodes));
        sort($normalized);

        if ($normalized === []) {
            return true;
        }

        $ownerRestricted = self::ownerRestrictedModuleCodes();
        sort($ownerRestricted);

        $restricted = self::restrictedModuleCodes();
        sort($restricted);

        return $normalized === $ownerRestricted || $normalized === $restricted;
    }
}
