<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Constants\Billing\PlanFeatures;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Services\Billing\Contracts\PlanLimitServiceInterface;
use App\Services\Billing\Contracts\UsageServiceInterface;
use Illuminate\Validation\ValidationException;

class PlanLimitService implements PlanLimitServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly UsageServiceInterface $usageService,
    ) {}

    public function getPlanLimits(int $facilityId): ?array
    {
        $subscription = $this->getAccessibleSubscription($facilityId);
        $plan = $subscription?->plan;

        if (! $plan) {
            return null;
        }

        return [
            'max_staff'              => $plan->max_staff,
            'max_departments'        => $plan->max_departments,
            'max_visits_per_month' => $plan->max_visits_per_month,
        ];
    }

    public function getPlanEnabledModuleCodes(int $facilityId): array
    {
        return $this->resolveEnabledModuleCodes($facilityId, false);
    }

    public function getAssignableModuleCodes(int $facilityId, bool $includeOwnerAdministration = false): array
    {
        return $this->resolveEnabledModuleCodes($facilityId, $includeOwnerAdministration);
    }

    public function filterModulesForPlan(int $facilityId, array $moduleCodes, bool $includeOwnerAdministration = false): array
    {
        $planModules = $this->getAssignableModuleCodes($facilityId, $includeOwnerAdministration);

        return PlanFeatures::intersectRoleModulesWithPlan($moduleCodes, $planModules);
    }

    public function assertModulesAllowed(int $facilityId, array $moduleCodes, bool $includeOwnerAdministration = false): void
    {
        $planModules = $this->getAssignableModuleCodes($facilityId, $includeOwnerAdministration);
        $invalid = array_values(array_diff($moduleCodes, $planModules));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'module_code' => [
                    'The following modules are not included in your facility subscription plan: '
                    .implode(', ', $invalid).'.',
                ],
            ]);
        }
    }

    public function assertCanAddStaff(int $facilityId, ?int $staffId = null): void
    {
        $limits = $this->getPlanLimits($facilityId);
        $maxStaff = $limits['max_staff'] ?? null;

        if ($maxStaff === null) {
            return;
        }

        if ($staffId !== null && $this->usageService->isStaffCountedTowardLimit($facilityId, $staffId)) {
            return;
        }

        $current = $this->usageService->getStaffCount($facilityId);

        if ($current >= $maxStaff) {
            throw ValidationException::withMessages([
                'staff' => [
                    "Staff limit reached ({$maxStaff}). Active assignments and pending invitations count toward this limit. "
                    .'Upgrade your plan or cancel pending invitations to invite more staff.',
                ],
            ]);
        }
    }

    public function assertCanAddDepartment(int $facilityId): void
    {
        $limits = $this->getPlanLimits($facilityId);
        $maxDepartments = $limits['max_departments'] ?? null;

        if ($maxDepartments === null) {
            return;
        }

        $current = $this->usageService->getDepartmentCount($facilityId);

        if ($current >= $maxDepartments) {
            throw ValidationException::withMessages([
                'department' => [
                    "Department limit reached ({$maxDepartments}). Upgrade your plan to add more departments.",
                ],
            ]);
        }
    }

    public function assertCanCreateVisit(int $facilityId): void
    {
        $limits = $this->getPlanLimits($facilityId);
        $maxVisits = $limits['max_visits_per_month'] ?? null;

        if ($maxVisits === null) {
            return;
        }

        $current = $this->usageService->getVisitsCount($facilityId);

        if ($current >= $maxVisits) {
            throw ValidationException::withMessages([
                'visit' => [
                    "Monthly visit limit reached ({$maxVisits}). Upgrade your plan to register more patient visits this month.",
                ],
            ]);
        }
    }

    protected function getAccessibleSubscription(int $facilityId): ?Subscription
    {
        return $this->subscriptionRepository->findAccessibleByFacility($facilityId);
    }

    /**
     * @return list<string>
     */
    protected function resolveEnabledModuleCodes(int $facilityId, bool $includeOwnerAdministration): array
    {
        $subscription = $this->getAccessibleSubscription($facilityId);

        if (! $subscription || ! $subscription->hasAccess()) {
            return $includeOwnerAdministration
                ? PlanFeatures::ownerRestrictedModuleCodes()
                : PlanFeatures::restrictedModuleCodes();
        }

        $features = $subscription->plan?->features;
        $codes = PlanFeatures::enabledModuleCodes(is_array($features) ? $features : null);

        return $includeOwnerAdministration
            ? PlanFeatures::ensureOwnerAdministration($codes)
            : $codes;
    }
}
