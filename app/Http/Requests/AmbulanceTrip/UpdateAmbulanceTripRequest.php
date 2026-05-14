<?php

namespace App\Http\Requests\AmbulanceTrip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmbulanceTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'patient_id' => ['nullable', 'exists:patients,id'],
            'visit_id' => ['nullable', 'exists:visits,id'],
            'ambulance_id' => ['nullable', 'exists:ambulances,id'],
            'dispatch_staff_id' => ['nullable', 'exists:staff,id'],
            'requesting_staff_id' => ['nullable', 'exists:staff,id'],
            'trip_type' => ['nullable', Rule::in(['emergency', 'non_emergency', 'inter_facility_transfer', 'standby', 'special_event'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'pickup_location' => ['nullable', 'string'],
            'pickup_facility_id' => ['nullable', 'exists:facilities,id'],
            'destination_location' => ['nullable', 'string'],
            'destination_facility_id' => ['nullable', 'exists:facilities,id'],
            'dispatch_notes' => ['nullable', 'string'],
            'trip_notes' => ['nullable', 'string'],
            'mileage' => ['nullable', 'numeric', 'min:0'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
