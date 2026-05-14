<?php

namespace App\Http\Requests\AmbulanceCrewMember;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmbulanceCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ambulance_id' => ['required', 'exists:ambulances,id'],
            'staff_id' => ['required', 'exists:staff,id'],
            'role' => ['required', Rule::in(['driver', 'attendant', 'paramedic', 'emt', 'nurse', 'doctor', 'crew_lead'])],
            'is_primary_driver' => ['nullable', 'boolean'],
            'certification_expiry' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
            'assigned_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
