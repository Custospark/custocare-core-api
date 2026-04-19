<?php
// app/Http/Requests/Allergy/UpdateAllergyRequest.php

namespace App\Http\Requests\Allergy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllergyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allergen' => 'sometimes|string|max:255',
            'reaction' => 'nullable|string|max:500',
            'severity' => 'sometimes|in:mild,moderate,severe',
            'clinical_notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'diagnosed_at' => 'nullable|date',
            'resolved_at' => 'nullable|date|after_or_equal:diagnosed_at',
        ];
    }
}