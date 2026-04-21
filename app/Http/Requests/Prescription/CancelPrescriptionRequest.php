<?php

declare(strict_types=1);

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', Rule::in([
                'Patient Requested Cancellation',
                'Medication Error - Wrong Drug',
                'Medication Error - Wrong Dose',
                'Allergy Discovered',
                'Adverse Reaction Reported',
                'Duplicate Prescription',
                'Prescription Expired',
                'Better Alternative Available',
                'Patient Deceased',
                'Insurance Denied (Patient Canceled)',
                'Out of Stock (Patient Canceled)'
            ])],
            'cancellation_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Please select a cancellation reason',
        ];
    }
}