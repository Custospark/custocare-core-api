<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AmbulanceCrewMemberResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'ambulance_id' => $this->ambulance_id,
            'staff_id' => $this->staff_id,
            'role' => $this->role,
            'is_primary_driver' => $this->is_primary_driver,
            'certification_expiry' => $this->certification_expiry?->toDateString(),
            'active' => $this->active,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'unassigned_at' => $this->unassigned_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),

            'staff' => fn() => $this->whenLoaded('staff', fn() => [
                'id' => $this->staff->id,
                'first_name' => $this->staff->first_name,
                'last_name' => $this->staff->last_name,
                'email' => $this->staff->email,
            ]),
            'ambulance' => fn() => $this->whenLoaded('ambulance', fn() => [
                'id' => $this->ambulance->id,
                'vehicle_identifier' => $this->ambulance->vehicle_identifier,
            ]),
        ];
    }
}
