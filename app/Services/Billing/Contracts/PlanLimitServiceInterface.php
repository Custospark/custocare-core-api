<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

interface PlanLimitServiceInterface
{
    /**
     * @return array{max_staff: int|null, max_departments: int|null, max_patients_per_month: int|null}|null
     */
    public function getPlanLimits(int $facilityId): ?array;

    /**
     * Module codes enabled by the facility's accessible subscription plan.
     *
     * @return list<string>
     */
    public function getPlanEnabledModuleCodes(int $facilityId): array;

    /**
     * Module codes that may be assigned at a facility.
     *
     * @return list<string>
     */
    public function getAssignableModuleCodes(int $facilityId, bool $includeOwnerAdministration = false): array;

    /**
     * @param  list<string>  $moduleCodes
     * @return list<string>
     */
    public function filterModulesForPlan(
        int $facilityId,
        array $moduleCodes,
        bool $includeOwnerAdministration = false,
    ): array;

    /**
     * @param  list<string>  $moduleCodes
     */
    public function assertModulesAllowed(
        int $facilityId,
        array $moduleCodes,
        bool $includeOwnerAdministration = false,
    ): void;

    /**
     * Ensures the facility can add another staff seat (assignment or pending invitation).
     *
     * @param  int|null  $staffId  When provided, skips the check if this staff is already counted.
     */
    public function assertCanAddStaff(int $facilityId, ?int $staffId = null): void;

    public function assertCanAddDepartment(int $facilityId): void;

    public function assertCanCreateVisit(int $facilityId): void;
}
