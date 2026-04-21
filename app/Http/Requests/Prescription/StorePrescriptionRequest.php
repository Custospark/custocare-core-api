<?php

declare(strict_types=1);

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'patient_id' => ['required', 'exists:patients,id'],
            'visit_id' => ['nullable', 'exists:visits,id'],
            'clinical_template_id' => ['nullable', 'exists:clinical_templates,id'],
            'prescription_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:prescription_date'],
            'status' => ['required', Rule::in([
                'Draft - Not Yet Finalized',
                'Active - Ready for Dispensing',
                'Partially Dispensed',
                'Fully Dispensed',
                'Expired - Past Valid Date',
                'Cancelled - No Longer Valid',
                'On Hold - Pending Review'
            ])],
            'prescription_type' => ['required', Rule::in([
                'New Prescription',
                'Refill Prescription',
                'Renewal (New Course)',
                'Emergency Prescription',
                'Standing Order',
                'Discharge Prescription',
                'Transfer Prescription'
            ])],
            'priority' => ['required', Rule::in([
                'Routine - Fill Within 24 Hours',
                'Urgent - Fill Within 4 Hours',
                'STAT - Fill Immediately',
                'Scheduled - Fill on Specific Date'
            ])],
            'diagnosis' => ['nullable', 'string'],
            'clinical_notes' => ['nullable', 'string'],
            'special_instructions' => ['nullable', 'string'],
            'allergy_check' => ['nullable', Rule::in([
                'No Known Allergies',
                'Allergies Checked - No Conflicts',
                'Allergy Warning - Overridden',
                'Allergy Alert - Changed Medication'
            ])],
            'allergy_notes' => ['nullable', 'string'],
            'prescribed_by' => ['nullable', 'exists:users,id'],
            'prescriber_type' => ['required', Rule::in([
                'Medical Doctor (MD)',
                'Doctor of Osteopathy (DO)',
                'Nurse Practitioner (NP)',
                'Physician Assistant (PA)',
                'Clinical Officer',
                'Dentist (DDS/DMD)',
                'Podiatrist (DPM)',
                'Optometrist (OD)',
                'Pharmacist (PharmD)',
                'Midwife (CNM/CM)'
            ])],
            'prescriber_license' => ['nullable', 'string', 'max:100'],
            'prescriber_contact' => ['nullable', 'string', 'max:100'],
            'prescription_format' => ['required', Rule::in([
                'Electronic (e-Prescription)',
                'Printed Paper Prescription',
                'Handwritten Prescription',
                'Faxed Prescription',
                'Verbal Order (Telephone)'
            ])],
            'patient_education_notes' => ['nullable', 'string'],
            'follow_up_instructions' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            
            // Items array
            'items' => ['required', 'array', 'min:1'],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.brand_name' => ['nullable', 'string', 'max:255'],
            'items.*.strength' => ['nullable', 'string', 'max:100'],
            'items.*.dosage_form' => ['required', 'string'],
            'items.*.dosage_quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.dosage_unit' => ['required', 'string'],
            'items.*.frequency' => ['required', 'string'],
            'items.*.duration_value' => ['required', 'integer', 'min:1'],
            'items.*.duration_unit' => ['required', Rule::in(['Day(s)', 'Week(s)', 'Month(s)', 'Year(s)'])],
            'items.*.route' => ['required', 'string'],
            'items.*.instructions' => ['nullable', 'string'],
            'items.*.as_needed' => ['boolean'],
            'items.*.as_needed_reason' => ['nullable', 'string', 'required_if:as_needed,true'],
            'items.*.administration_instructions' => ['required', 'string'],
            'items.*.refills' => ['required', 'string'],
            'items.*.refill_instructions' => ['nullable', 'string'],
            'items.*.medication_type' => ['nullable', 'string'],
            'items.*.monitoring_required' => ['nullable', 'string'],
            'items.*.common_side_effects' => ['nullable', 'string'],
            'items.*.clinical_reasoning' => ['nullable', 'string'],
            'items.*.substitution_instructions' => ['nullable', 'string'],
            'items.*.substitution' => ['required', Rule::in([
                'Generic substitution allowed',
                'Brand name only - No substitution',
                'Therapeutic substitution allowed (same class)',
                'Dispense as written (DAW)'
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one medication item is required',
            'items.*.medication_name.required' => 'Medication name is required for each item',
            'items.*.dosage_quantity.required' => 'Dosage quantity is required for each medication',
            'items.*.frequency.required' => 'Frequency is required for each medication',
            'items.*.duration_value.required' => 'Duration is required for each medication',
        ];
    }
}