<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Services\Billing\BillingFacilitySummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact facility block for platform billing tables and detail modals. */
class BillingFacilitySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->owner_summary ?? null;

        return [
            'id'             => $this->id,
            'facility_name'  => $this->facility_name,
            'facility_code'  => $this->facility_code,
            'location_label' => BillingFacilitySummaryService::locationLabel($this->resource),
            'phone'          => $this->main_phone,
            'email'          => $this->email,
            'owner'          => $owner ? [
                'name'  => $owner['name'] ?? null,
                'email' => $owner['email'] ?? null,
                'phone' => $owner['phone'] ?? null,
            ] : null,
        ];
    }
}
