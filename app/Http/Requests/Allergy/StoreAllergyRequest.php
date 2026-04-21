<?php
// app/Http/Requests/Allergy/StoreAllergyRequest.php

namespace App\Http\Requests\Allergy;

use Illuminate\Foundation\Http\FormRequest;

class StoreAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy check if needed
    }

    public function rules(): array
    {
        return [
            'allergen' => 'required|string|max:255',
            'reaction' => 'nullable|string|max:500',
            'severity' => 'required|in:mild,moderate,severe',
            'clinical_notes' => 'nullable|string',
            'diagnosed_at' => 'nullable|date',
            'visit_id' => 'nullable|exists:visits,id',
            'patient_id' => 'nullable|exists:patients,id',
        ];
    }

    public function messages(): array
    {
        return [
            'allergen.required' => 'The allergen name is required.',
            'severity.required' => 'Please specify the severity level.',
            'severity.in' => 'Severity must be mild, moderate, or severe.',
        ];
    }
}