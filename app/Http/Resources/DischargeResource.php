<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DischargeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'visit_uuid' => $this->visit_uuid,
            'patient_id' => $this->patient_id,
            'discharged_at' => $this->discharged_at,
            'discharge_disposition' => $this->discharge_disposition,
            'discharge_diagnosis' => $this->discharge_diagnosis,
            'discharge_instructions' => $this->discharge_instructions,
            'discharge_medications' => $this->discharge_medications ?? [],
            'followup_scheduled_at' => $this->followup_scheduled_at,
            'followup_provider' => new StaffResource($this->whenLoaded('followupProvider')),
            'discharged_by' => new StaffResource($this->whenLoaded('dischargedBy')),
            'is_discharged' => !is_null($this->discharged_at),
        ];
    }
}
