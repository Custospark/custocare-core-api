<?php

namespace App\Http\Requests\AmbulanceTripLog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmbulanceTripLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(['status_change', 'location_update', 'patient_condition', 'note', 'handoff', 'delay'])],
            'description' => ['nullable', 'string'],
            'recorded_at' => ['nullable', 'date'],
            'recorded_by_staff_id' => ['nullable', 'exists:staff,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
