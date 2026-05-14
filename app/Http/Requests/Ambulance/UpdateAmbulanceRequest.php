<?php

namespace App\Http\Requests\Ambulance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmbulanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'crew_team_lead_staff_id' => ['nullable', 'exists:staff,id'],
            'vehicle_identifier' => ['nullable', 'string', 'max:50', Rule::unique('ambulances', 'vehicle_identifier')->ignore($this->route('ambulance'), 'ambulance_uuid')],
            'vehicle_type' => ['nullable', 'string', 'max:50', Rule::in(['bls', 'als', 'critical_care', 'patient_transport', 'type_i', 'type_ii', 'type_iii', 'medium_duty', 'specialty', 'other'])],
            'equipment_level' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['available', 'in_service', 'out_of_service', 'maintenance', 'decommissioned'])],
            'last_service_date' => ['nullable', 'date'],
            'next_service_due_date' => ['nullable', 'date'],
            'current_mileage' => ['nullable', 'integer', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:255'],
            'features' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
