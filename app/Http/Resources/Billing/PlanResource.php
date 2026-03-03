<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'description'            => $this->description,
            'pricing'                => [
                'usd'                => (float) $this->price_usd,
                'ugx'                => (float) $this->price_ugx,
                'billing_cycle'      => $this->billing_cycle,
            ],
            'onboarding_fee'         => [
                'usd'                => (float) $this->onboarding_fee_usd,
                'ugx'                => (float) $this->onboarding_fee_ugx,
                'applicable'         => $this->hasOnboardingFee(),
            ],
            'trial_days'             => $this->trial_days,
            'limits'                 => [
                'max_staff'              => $this->max_staff,
                'max_departments'        => $this->max_departments,
                'max_patients_per_month' => $this->max_patients_per_month,
            ],
            'features'               => $this->features ?? [],
            'is_popular'             => $this->is_popular,
            'is_active'              => $this->is_active,
            'sort_order'             => $this->sort_order,
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
