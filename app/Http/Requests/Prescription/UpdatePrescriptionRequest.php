<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Prescription;

class UpdatePrescriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $prescription = $this->route('prescription');
        
        // Check if user has permission to update this prescription
        return Auth::check() && Auth::user()->can('update', $prescription);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $prescription = $this->route('prescription');
        
        return [
            'visit_id' => 'nullable|exists:visits,id',
            'prescriber_npi' => 'nullable|string|max:20',
            'prescriber_dea_number' => 'nullable|string|max:20',
            'medication_name' => 'nullable|string|max:300',
            'generic_name' => 'nullable|string|max:300',
            'ndc_code' => 'nullable|string|max:20',
            'controlled_substance_schedule' => 'nullable|in:I,II,III,IV,V,non_controlled',
            'dosage_strength' => 'nullable|string|max:100',
            'dosage_form' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:100',
            'sig_instructions' => 'nullable|string',
            'pharmacist_notes' => 'nullable|string',
            'quantity_prescribed' => 'nullable|numeric|min:0.01|max:999999.99',
            'quantity_unit' => 'nullable|string|max:50',
            'refills_allowed' => 'nullable|integer|min:0|max:99',
            'refills_remaining' => 'nullable|integer|min:0|max:99',
            'days_supply' => 'nullable|integer|min:1|max:365',
            'diagnosis_codes' => 'nullable|array',
            'diagnosis_codes.*' => 'string|max:20',
            'clinical_indication' => 'nullable|string|max:1000',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after:valid_from',
            'do_not_fill_before' => 'nullable|date|before:valid_to',
            'requires_prior_authorization' => 'nullable|boolean',
            'prior_authorization_number' => 'nullable|string|max:100',
            'prior_auth_status' => 'nullable|in:not_required,pending,approved,denied',
            'is_electronic_prescription' => 'nullable|boolean',
            'erx_message_id' => 'nullable|string|max:100',
            'transmit_to_pharmacy' => 'nullable|string|max:300',
            'pharmacy_ncpdp_id' => 'nullable|string|max:20',
            'dispense_status' => 'nullable|in:pending,transmitted,received_by_pharmacy,in_progress,ready_for_pickup,dispensed,not_picked_up,cancelled,discontinued',
            'is_high_risk_medication' => 'nullable|boolean',
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
            'valid_to.after' => 'Valid to date must be after valid from date.',
            'do_not_fill_before.before' => 'Do not fill before date must be before valid to date.',
            'refills_remaining.max' => 'Refills remaining cannot exceed refills allowed.',
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
            'valid_from' => 'valid from date',
            'valid_to' => 'valid to date',
            'do_not_fill_before' => 'do not fill before date',
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
                'message' => 'You are not authorized to update this prescription.',
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
        $prescription = $this->route('prescription');
        
        // Prevent updating certain fields if prescription has been transmitted
        if ($prescription->transmitted_at) {
            $this->merge([
                'medication_name' => $prescription->medication_name,
                'dosage_strength' => $prescription->dosage_strength,
                'dosage_form' => $prescription->dosage_form,
                'route' => $prescription->route,
                'sig_instructions' => $prescription->sig_instructions,
                'quantity_prescribed' => $prescription->quantity_prescribed,
                'quantity_unit' => $prescription->quantity_unit,
            ]);
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

        // If updating to transmitted status, set transmitted_at
        if ($this->has('dispense_status') && $this->input('dispense_status') === 'transmitted') {
            $this->merge([
                'transmitted_at' => now(),
            ]);
        }

        // If discontinuing, require status_reason
        if ($this->has('status') && $this->input('status') === 'discontinued') {
            if (!$this->has('status_reason') || empty($this->input('status_reason'))) {
                $this->merge([
                    'status_reason' => 'Discontinued by provider',
                ]);
            }
            
            // Set discontinued_at if not provided
            if (!$this->has('discontinued_at')) {
                $this->merge([
                    'discontinued_at' => now(),
                    'discontinued_by_staff_id' => Auth::id(),
                ]);
            }
        }
    }
}