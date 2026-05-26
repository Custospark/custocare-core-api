<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionScheduledChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'change_type'  => $this->change_type,
            'status'       => $this->status,
            'effective_at' => $this->effective_at?->toISOString(),
            'from_plan'    => new PlanResource($this->whenLoaded('fromPlan')),
            'to_plan'      => $this->to_plan_id
                ? new PlanResource($this->whenLoaded('toPlan'))
                : null,
            'metadata'     => $this->metadata,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
