<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AmbulanceTripResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'trip_uuid' => $this->trip_uuid,
            'facility_id' => $this->facility_id,
            'patient_id' => $this->patient_id,
            'visit_id' => $this->visit_id,
            'ambulance_id' => $this->ambulance_id,
            'dispatch_staff_id' => $this->dispatch_staff_id,
            'requesting_staff_id' => $this->requesting_staff_id,
            'trip_type' => $this->trip_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'pickup_location' => $this->pickup_location,
            'pickup_facility_id' => $this->pickup_facility_id,
            'destination_location' => $this->destination_location,
            'destination_facility_id' => $this->destination_facility_id,
            'dispatch_notes' => $this->dispatch_notes,
            'trip_notes' => $this->trip_notes,
            'mileage' => $this->mileage,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'en_route_at' => $this->en_route_at?->toISOString(),
            'on_scene_at' => $this->on_scene_at?->toISOString(),
            'patient_contact_at' => $this->patient_contact_at?->toISOString(),
            'depart_scene_at' => $this->depart_scene_at?->toISOString(),
            'at_destination_at' => $this->at_destination_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'facility' => fn() => $this->whenLoaded('facility', fn() => [
                'id' => $this->facility->id, 'facility_name' => $this->facility->facility_name,
            ]),
            'patient' => fn() => $this->whenLoaded('patient', fn() => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->user?->first_name,
                'last_name' => $this->patient->user?->last_name,
            ]),
            'ambulance' => fn() => $this->whenLoaded('ambulance', fn() => [
                'id' => $this->ambulance->id,
                'vehicle_identifier' => $this->ambulance->vehicle_identifier,
            ]),
            'dispatch_staff' => fn() => $this->whenLoaded('dispatchStaff', fn() => [
                'id' => $this->dispatchStaff->id,
                'first_name' => $this->dispatchStaff->first_name,
                'last_name' => $this->dispatchStaff->last_name,
            ]),
            'requesting_staff' => fn() => $this->whenLoaded('requestingStaff', fn() => [
                'id' => $this->requestingStaff->id,
                'first_name' => $this->requestingStaff->first_name,
                'last_name' => $this->requestingStaff->last_name,
            ]),
        ];
    }
}
