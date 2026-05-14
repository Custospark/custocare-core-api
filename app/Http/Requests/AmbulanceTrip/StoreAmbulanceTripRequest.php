<?php

namespace App\Http\Requests\AmbulanceTrip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmbulanceTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'visit_id' => ['nullable', 'exists:visits,id'],
            'ambulance_id' => ['nullable', 'exists:ambulances,id'],
            'dispatch_staff_id' => ['nullable', 'exists:staff,id'],
            'requesting_staff_id' => ['nullable', 'exists:staff,id'],
            'trip_type' => ['required', Rule::in(['emergency', 'non_emergency', 'inter_facility_transfer', 'standby', 'special_event'])],
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

    public function messages(): array
    {
        return [
            'facility_id.required' => 'Dispatching facility is required.',
            'patient_id.required' => 'Patient is required.',
            'trip_type.required' => 'Trip type is required.',
            'trip_type.in' => 'Trip type must be one of: emergency, non_emergency, inter_facility_transfer, standby, special_event.',
        ];
    }
}
