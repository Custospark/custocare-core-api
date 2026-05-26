<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Facility;
use App\Services\Statistics\PlatformAdminService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Enriches billing list responses with facility location + owner contact for platform admins.
 */
class BillingFacilitySummaryService
{
    public function __construct(
        private readonly PlatformAdminService $platformAdminService,
    ) {}

    public function enrichSubscriptionPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $this->attachOwnerSummaries(
            $paginator->getCollection()->pluck('facility')->filter(),
        );

        return $paginator;
    }

    public function enrichPaymentPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $this->attachOwnerSummaries(
            $paginator->getCollection()->pluck('facility')->filter(),
        );

        return $paginator;
    }

    /**
     * @param  Collection<int, Facility>  $facilities
     */
    private function attachOwnerSummaries(Collection $facilities): void
    {
        if ($facilities->isEmpty()) {
            return;
        }

        $ids = $facilities->pluck('id')->unique()->values()->all();
        $owners = $this->platformAdminService->getOwnersForFacilityIds($ids);

        foreach ($facilities as $facility) {
            if (! $facility instanceof Facility) {
                continue;
            }
            $facility->setAttribute(
                'owner_summary',
                $owners->get($facility->id),
            );
        }
    }

    public static function locationLabel(Facility $facility): string
    {
        $parts = array_filter([
            $facility->city,
            $facility->state_province,
            $facility->country_code,
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $facility->full_address
            ?? $facility->address_line1
            ?? '';
    }
}
