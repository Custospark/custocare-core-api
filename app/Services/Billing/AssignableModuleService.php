<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Module;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Services\Billing\Contracts\AssignableModuleServiceInterface;
use App\Services\Billing\Contracts\PlanLimitServiceInterface;

class AssignableModuleService implements AssignableModuleServiceInterface
{
    /** Modules never offered in invitation / staff-assignment pickers. */
    private const EXCLUDED_INVITATION_MODULE_CODES = [
        'account',
        'platform_administration',
    ];

    /** Always grant access regardless of plan tier (patient portal + hub). */
    private const ALWAYS_ACCESSIBLE_MODULE_CODES = [
        'patient_dashboard',
        'custocare_hub',
    ];

    public function __construct(
        private readonly PlanLimitServiceInterface $planLimitService,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    public function getForFacility(int $facilityId, bool $includeOwnerAdministration): array
    {
        $assignableCodes = $this->planLimitService->getAssignableModuleCodes(
            $facilityId,
            $includeOwnerAdministration,
        );

        $invitationCodes = array_values(array_unique(array_merge(
            array_values(array_filter(
                $assignableCodes,
                fn (string $code) => ! in_array($code, self::EXCLUDED_INVITATION_MODULE_CODES, true),
            )),
            self::ALWAYS_ACCESSIBLE_MODULE_CODES,
        )));

        $modules = Module::query()
            ->where('is_active', true)
            ->whereIn('code', $invitationCodes)
            ->orderBy('name')
            ->get();

        $subscription = $this->subscriptionRepository->findAccessibleByFacility($facilityId);
        $plan = $subscription?->plan;

        return [
            'modules' => $modules,
            'allowed_module_codes' => $invitationCodes,
            'plan_enabled_module_codes' => $this->planLimitService->getPlanEnabledModuleCodes($facilityId),
            'plan' => $plan ? [
                'slug' => $plan->slug ?? null,
                'name' => $plan->name ?? null,
            ] : null,
        ];
    }
}
