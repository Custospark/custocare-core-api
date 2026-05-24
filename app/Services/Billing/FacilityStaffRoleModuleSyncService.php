<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Constants\Billing\PlanFeatures;
use App\Models\FacilityOwner;
use App\Models\FacilityStaffRole;
use App\Models\Module;
use App\Models\RoleModuleDefault;
use App\Models\Subscription;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use Illuminate\Support\Facades\Log;

class FacilityStaffRoleModuleSyncService implements FacilityStaffRoleModuleSyncServiceInterface
{
    public function syncForSubscription(Subscription $subscription): void
    {
        $subscription->loadMissing('plan');

        if (! $subscription->hasAccess()) {
            return;
        }

        $planModuleCodes = PlanFeatures::enabledModuleCodes(
            is_array($subscription->plan?->features) ? $subscription->plan->features : null,
        );

        $facilityId = (int) $subscription->facility_id;

        $ownerStaffIds = FacilityOwner::query()
            ->where('facility_id', $facilityId)
            ->pluck('staff_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $assignments = FacilityStaffRole::query()
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->get();

        foreach ($assignments as $assignment) {
            $isOwner = in_array((int) $assignment->staff_id, $ownerStaffIds, true);

            $moduleCodes = $isOwner
                ? PlanFeatures::ensureOwnerAdministration($planModuleCodes)
                : PlanFeatures::intersectRoleModulesWithPlan(
                    $this->resolveRoleDefaultModules((string) $assignment->role_code),
                    $planModuleCodes,
                );

            $assignment->update(['module_code' => $moduleCodes]);
        }

        Log::info('[Billing] Facility staff role modules synced after subscription access', [
            'subscription_id' => $subscription->id,
            'facility_id' => $facilityId,
            'assignments_updated' => $assignments->count(),
            'plan_modules' => $planModuleCodes,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function resolveRoleDefaultModules(string $roleCode): array
    {
        $activeModuleCodes = Module::query()
            ->where('is_active', true)
            ->pluck('code')
            ->all();

        $default = RoleModuleDefault::query()
            ->where('role_code', $roleCode)
            ->where('default_access', true)
            ->first();

        if (! $default || empty($default->module_code)) {
            return [];
        }

        $roleModules = is_array($default->module_code) ? $default->module_code : [];

        return array_values(array_intersect($roleModules, $activeModuleCodes));
    }
}
