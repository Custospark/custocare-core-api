<?php
// app/Http/Resources/AllergyResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllergyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'allergen' => $this->allergen,
            'reaction' => $this->reaction,
            'severity' => $this->severity,
            'clinical_notes' => $this->clinical_notes,
            'is_active' => $this->is_active,
            'is_severe' => $this->isSevere(),
            'is_resolved' => $this->isResolved(),
            'diagnosed_at' => $this->diagnosed_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'recorded_by' => $this->whenLoaded('recordedBy', function () {
                return [
                    'id' => $this->recordedBy->id,
                    'name' => $this->recordedBy->first_name . ' ' . $this->recordedBy->last_name,
                ];
            }),
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'visit_date' => $this->visit->visit_date_time?->toISOString(),
                    'facility_name' => $this->visit->facility?->facility_name, // Add facility name
                    'facility_main_phone' => $this->visit->facility?->main_phone, // Add facility name
                    'facility_id' => $this->visit->facility_id, // Optional: include facility ID
                ];
            }),
        ];
    }
}