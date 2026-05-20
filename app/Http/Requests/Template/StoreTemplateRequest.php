<?php

declare(strict_types=1);

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in([
                'General Practice',
                'Emergency Medicine',
                'Pediatrics',
                'Geriatrics',
                'Cardiology',
                'Neurology',
                'Pulmonology',
                'Gastroenterology',
                'Endocrinology',
                'Infectious Diseases',
                'Psychiatry',
                'Obstetrics & Gynecology',
                'Orthopedics',
                'Dermatology',
                'Ophthalmology',
                'Dentistry',
                'Urology',
                'Nephrology',
                'Oncology',
                'Rheumatology',
                'Allergy & Immunology',
                'Sports Medicine',
                'Pain Management',
                'Palliative Care'
            ])],
            'default_diagnosis' => ['nullable', 'string'],
            'default_notes' => ['nullable', 'string'],
            'patient_instructions' => ['nullable', 'string'],
            'default_medications' => ['nullable', 'array'],
            'default_medications.*.medication_name' => ['required_with:default_medications', 'string'],
            'default_medications.*.dosage_form' => ['required_with:default_medications', 'string'],
            'default_medications.*.dosage_quantity' => ['required_with:default_medications', 'numeric', 'min:0.01'],
            'default_medications.*.dosage_unit' => ['required_with:default_medications', 'string'],
            'default_medications.*.frequency' => ['required_with:default_medications', 'string'],
            'default_medications.*.duration_value' => ['required_with:default_medications', 'integer', 'min:1'],
            'default_medications.*.duration_unit' => ['required_with:default_medications', 'string'],
            'visibility' => ['required', Rule::in([
                'System Wide (All Facilities)',
                'This Facility Only',
                'My Department Only',
                'Only Me (Private)'
            ])],
        ];
    }
}