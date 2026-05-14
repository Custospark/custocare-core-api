<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AmbulanceTripLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'event_type' => $this->event_type,
            'description' => $this->description,
            'recorded_at' => $this->recorded_at?->toISOString(),
            'recorded_by_staff_id' => $this->recorded_by_staff_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),

            'recorded_by' => fn() => $this->whenLoaded('recordedBy', fn() => [
                'id' => $this->recordedBy->id,
                'first_name' => $this->recordedBy->first_name,
                'last_name' => $this->recordedBy->last_name,
            ]),
        ];
    }
}
