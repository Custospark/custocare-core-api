<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Subscription;

interface FacilityStaffRoleModuleSyncServiceInterface
{
    /**
     * Expand persisted facility_staff_roles.module_code after subscription access is granted.
     * Owners receive all plan modules; staff receive role defaults intersected with the plan.
     */
    public function syncForSubscription(Subscription $subscription): void;
}
