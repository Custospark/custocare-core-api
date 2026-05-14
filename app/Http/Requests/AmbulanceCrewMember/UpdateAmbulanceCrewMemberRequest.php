<?php

namespace App\Http\Requests\AmbulanceCrewMember;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmbulanceCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ambulance_id' => ['nullable', 'exists:ambulances,id'],
            'staff_id' => ['nullable', 'exists:staff,id'],
            'role' => ['nullable', Rule::in(['driver', 'attendant', 'paramedic', 'emt', 'nurse', 'doctor', 'crew_lead'])],
            'is_primary_driver' => ['nullable', 'boolean'],
            'certification_expiry' => ['nullable', 'date'],
            'active' => ['nullable', 'boolean'],
            'unassigned_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
