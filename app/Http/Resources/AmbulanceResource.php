<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AmbulanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'ambulance_uuid' => $this->ambulance_uuid,
            'facility_id' => $this->facility_id,
            'crew_team_lead_staff_id' => $this->crew_team_lead_staff_id,
            'vehicle_identifier' => $this->vehicle_identifier,
            'vehicle_type' => $this->vehicle_type,
            'equipment_level' => $this->equipment_level,
            'status' => $this->status,
            'last_service_date' => $this->last_service_date?->toDateString(),
            'next_service_due_date' => $this->next_service_due_date?->toDateString(),
            'current_mileage' => $this->current_mileage,
            'capacity' => $this->capacity,
            'features' => $this->features,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'facility' => fn() => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id,
                'facility_uuid' => $this->facility->facility_uuid,
                'facility_name' => $this->facility->facility_name,
            ]),
            'crew_team_lead' => fn() => $this->whenLoaded('crewTeamLead', fn() => [
                'id' => $this->crewTeamLead->id,
                'first_name' => $this->crewTeamLead->first_name,
                'last_name' => $this->crewTeamLead->last_name,
            ]),
        ];
    }
}
