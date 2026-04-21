<?php

declare(strict_types=1);

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', Rule::in([
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
            'is_active' => ['nullable', 'boolean'],
            'visibility' => ['nullable', Rule::in([
                'System Wide (All Facilities)',
                'This Facility Only',
                'My Department Only',
                'Only Me (Private)'
            ])],
        ];
    }
}