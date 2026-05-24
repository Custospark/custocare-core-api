<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use Illuminate\Console\Command;

class SyncFacilitySubscriptionModules extends Command
{
    protected $signature = 'billing:sync-facility-modules {--facility= : Optional facility ID}';

    protected $description = 'Expand facility staff role modules for subscriptions with active access';

    public function handle(FacilityStaffRoleModuleSyncServiceInterface $moduleSyncService): int
    {
        $facilityId = $this->option('facility');

        $query = Subscription::query()->with('plan');

        if ($facilityId) {
            $query->where('facility_id', (int) $facilityId);
        }

        $synced = 0;

        $query->get()->each(function (Subscription $subscription) use ($moduleSyncService, &$synced) {
            if (! $subscription->hasAccess()) {
                return;
            }

            $moduleSyncService->syncForSubscription($subscription);
            $synced++;
            $this->line("Synced facility #{$subscription->facility_id} (subscription #{$subscription->id})");
        });

        $this->info("Done. {$synced} subscription(s) synced.");

        return self::SUCCESS;
    }
}
