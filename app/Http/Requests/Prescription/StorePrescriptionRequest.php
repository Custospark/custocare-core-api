<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;

class StorePrescriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Check if user has permission to create prescriptions
        return Auth::check() && Auth::user()->can('create', \App\Models\Prescription::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'visit_id' => 'nullable|exists:visits,id',
            'prescribing_provider_staff_id' => 'required|exists:staff,id',
            'prescriber_npi' => 'nullable|string|max:20',
            'prescriber_dea_number' => 'nullable|string|max:20',
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'medication_name' => 'required|string|max:300',
            'generic_name' => 'nullable|string|max:300',
            'ndc_code' => 'nullable|string|max:20',
            'controlled_substance_schedule' => 'nullable|in:I,II,III,IV,V,non_controlled',
            'dosage_strength' => 'required|string|max:100',
            'dosage_form' => 'required|string|max:100',
            'route' => 'required|string|max:100',
            'sig_instructions' => 'required|string',
            'pharmacist_notes' => 'nullable|string',
            'quantity_prescribed' => 'required|numeric|min:0.01|max:999999.99',
            'quantity_unit' => 'required|string|max:50',
            'refills_allowed' => 'integer|min:0|max:99',
            'refills_remaining' => 'integer|min:0|max:99',
            'days_supply' => 'nullable|integer|min:1|max:365',
            'diagnosis_codes' => 'nullable|array',
            'diagnosis_codes.*' => 'string|max:20',
            'clinical_indication' => 'nullable|string|max:1000',
            'valid_from' => 'required|date|after_or_equal:today',
            'valid_to' => 'required|date|after:valid_from',
            'do_not_fill_before' => 'nullable|date|after_or_equal:today|before:valid_to',
            'requires_prior_authorization' => 'boolean',
            'prior_authorization_number' => 'nullable|string|max:100',
            'prior_auth_status' => 'nullable|in:not_required,pending,approved,denied',
            'is_electronic_prescription' => 'boolean',
            'transmit_to_pharmacy' => 'nullable|string|max:300',
            'pharmacy_ncpdp_id' => 'nullable|string|max:20',
            'is_high_risk_medication' => 'boolean',
            'safety_monitoring_required' => 'nullable|array',
            'special_instructions' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,completed,cancelled,discontinued,expired,on_hold',
            'status_reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'prescribing_provider_staff_id.required' => 'Prescribing provider is required.',
            'prescribing_provider_staff_id.exists' => 'Selected provider does not exist.',
            'inventory_item_id.required' => 'Medication item is required.',
            'inventory_item_id.exists' => 'Selected medication item does not exist.',
            'medication_name.required' => 'Medication name is required.',
            'dosage_strength.required' => 'Dosage strength is required.',
            'dosage_form.required' => 'Dosage form is required.',
            'route.required' => 'Administration route is required.',
            'sig_instructions.required' => 'Patient instructions are required.',
            'quantity_prescribed.required' => 'Quantity prescribed is required.',
            'quantity_prescribed.min' => 'Quantity must be greater than 0.',
            'quantity_unit.required' => 'Quantity unit is required.',
            'valid_from.required' => 'Valid from date is required.',
            'valid_from.after_or_equal' => 'Valid from date cannot be in the past.',
            'valid_to.required' => 'Valid to date is required.',
            'valid_to.after' => 'Valid to date must be after valid from date.',
            'do_not_fill_before.before' => 'Do not fill before date must be before valid to date.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'patient',
            'prescribing_provider_staff_id' => 'prescribing provider',
            'inventory_item_id' => 'medication item',
            'medication_name' => 'medication name',
            'dosage_strength' => 'dosage strength',
            'dosage_form' => 'dosage form',
            'route' => 'administration route',
            'sig_instructions' => 'patient instructions',
            'quantity_prescribed' => 'quantity prescribed',
            'quantity_unit' => 'quantity unit',
            'valid_from' => 'valid from date',
            'valid_to' => 'valid to date',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create prescriptions.',
            ], 403)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Set default facility ID if not provided
        if (!$this->has('facility_id') && Auth::check()) {
            $this->merge([
                'facility_id' => Auth::user()->facility_id,
            ]);
        }

        // Set default values for electronic prescriptions
        if ($this->has('is_electronic_prescription') && $this->input('is_electronic_prescription')) {
            if (!$this->has('erx_message_id')) {
                $this->merge([
                    'erx_message_id' => 'ERX-' . time() . '-' . rand(1000, 9999),
                ]);
            }
        }

        // Convert comma-separated diagnosis codes to array
        if ($this->has('diagnosis_codes') && is_string($this->input('diagnosis_codes'))) {
            $codes = array_map('trim', explode(',', $this->input('diagnosis_codes')));
            $this->merge([
                'diagnosis_codes' => array_filter($codes),
            ]);
        }

        // Ensure refills_remaining is not greater than refills_allowed
        if ($this->has('refills_allowed') && $this->has('refills_remaining')) {
            $refillsAllowed = (int) $this->input('refills_allowed');
            $refillsRemaining = (int) $this->input('refills_remaining');
            
            if ($refillsRemaining > $refillsAllowed) {
                $this->merge([
                    'refills_remaining' => $refillsAllowed,
                ]);
            }
        }

        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'active',
            ]);
        }

        // Set default dispense status if not provided
        if (!$this->has('dispense_status')) {
            $this->merge([
                'dispense_status' => 'pending',
            ]);
        }
    }
}