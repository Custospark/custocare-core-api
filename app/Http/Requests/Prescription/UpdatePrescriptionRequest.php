<?php

declare(strict_types=1);

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'exists:visits,id'],
            'status' => ['nullable', Rule::in([
                'Draft - Not Yet Finalized',
                'Active - Ready for Dispensing',
                'Partially Dispensed',
                'Fully Dispensed',
                'Expired - Past Valid Date',
                'Cancelled - No Longer Valid',
                'On Hold - Pending Review'
            ])],
            'diagnosis' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'special_instructions' => ['nullable', 'string'],
            'patient_education_notes' => ['nullable', 'string'],
            'follow_up_instructions' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            
            // Optional items update
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'exists:prescription_items,id'],
            'items.*.medication_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.dosage_quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.frequency' => ['required_with:items', 'string'],
            'items.*.duration_value' => ['required_with:items', 'integer', 'min:1'],
            'items.*.duration_unit' => ['required_with:items', Rule::in(['Day(s)', 'Week(s)', 'Month(s)', 'Year(s)'])],
        ];
    }
}