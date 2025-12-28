<?php

namespace App\Http\Requests\MedicationDispense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

class StoreMedicationDispenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by policy
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'visit_id' => 'nullable|integer|exists:visits,id',
            'prescription_id' => 'required|integer|exists:prescriptions,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'prescription_details_snapshot' => 'required|array',
            'prescription_details_snapshot.medication_id' => 'required|integer',
            'prescription_details_snapshot.medication_name' => 'required|string|max:255',
            'prescription_details_snapshot.dosage' => 'required|string|max:100',
            'prescription_details_snapshot.frequency' => 'required|string|max:100',
            'prescription_details_snapshot.route' => 'required|string|max:50',
            'dispensed_inventory_ledger_id' => 'nullable|integer|exists:inventory_ledger,id',
            'quantity_dispensed' => 'required|numeric|min:0.01|max:999999.99',
            'quantity_unit' => 'required|string|max:50',
            'lot_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after:today',
            'dispensed_by_staff_id' => 'required|integer|exists:staff,id',
            'dispensed_at' => 'nullable|date|before_or_equal:now',
            'checked_by_staff_id' => 'nullable|integer|exists:staff,id',
            'checked_at' => 'nullable|date|after:dispensed_at',
            'pharmacist_notes' => 'nullable|string|max:2000',
            'patient_counseling_provided' => 'boolean',
            'medication_guide_provided' => 'boolean',
            'patient_education_topics' => 'nullable|string|max:1000',
            'patient_questions_addressed' => 'nullable|string|max:1000',
            'dispensed_instructions' => 'nullable|string|max:2000',
            'followup_instructions' => 'nullable|string|max:2000',
            'warning_labels_applied' => 'nullable|array',
            'safety_checks_performed' => 'required|array',
            'all_safety_checks_passed' => 'boolean',
            'safety_check_overrides' => 'nullable|array',
            'override_justification' => 'nullable|string|max:1000',
            'delivery_method' => 'nullable|in:pickup_in_person,mail_order,delivery_service,administered_in_facility,sent_to_home_health',
            'picked_up_by_name' => 'nullable|string|max:200',
            'pickup_id_verified' => 'nullable|string|max:100',
            'copay_collected' => 'nullable|numeric|min:0|max:999999.99',
            'total_cost_to_patient' => 'nullable|numeric|min:0|max:99999999.99',
            'insurance_payment' => 'nullable|numeric|min:0|max:99999999.99',
            'metadata' => 'nullable|array'
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
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'Selected facility does not exist',
            'prescription_id.required' => 'Prescription ID is required',
            'prescription_id.exists' => 'Selected prescription does not exist',
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'Selected patient does not exist',
            'prescription_details_snapshot.required' => 'Prescription details are required',
            'prescription_details_snapshot.array' => 'Prescription details must be in array format',
            'quantity_dispensed.required' => 'Quantity dispensed is required',
            'quantity_dispensed.min' => 'Quantity must be at least 0.01',
            'quantity_unit.required' => 'Quantity unit is required',
            'dispensed_by_staff_id.required' => 'Dispensing staff ID is required',
            'dispensed_by_staff_id.exists' => 'Selected staff member does not exist',
            'expiry_date.after' => 'Expiry date must be in the future',
            'checked_at.after' => 'Check time must be after dispense time',
            'delivery_method.in' => 'Invalid delivery method selected',
            'copay_collected.min' => 'Copay cannot be negative',
            'total_cost_to_patient.min' => 'Total cost cannot be negative',
            'insurance_payment.min' => 'Insurance payment cannot be negative'
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
            'facility_id' => 'facility',
            'visit_id' => 'visit',
            'prescription_id' => 'prescription',
            'patient_id' => 'patient',
            'dispensed_by_staff_id' => 'dispensing staff',
            'checked_by_staff_id' => 'checking pharmacist'
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
        Log::warning('Medication dispense store validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['password', 'password_confirmation'])
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray()
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
        Log::warning('Unauthorized attempt to create medication dispense', [
            'user_id' => $this->user()?->id
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create medication dispenses'
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
        // Ensure boolean fields are properly cast
        $this->merge([
            'patient_counseling_provided' => filter_var($this->patient_counseling_provided, FILTER_VALIDATE_BOOLEAN),
            'medication_guide_provided' => filter_var($this->medication_guide_provided, FILTER_VALIDATE_BOOLEAN),
            'all_safety_checks_passed' => filter_var($this->all_safety_checks_passed, FILTER_VALIDATE_BOOLEAN),
        ]);

        // Set default dispensed_at to now if not provided
        if (!$this->has('dispensed_at')) {
            $this->merge(['dispensed_at' => now()]);
        }

        // Ensure quantity is properly formatted
        if ($this->has('quantity_dispensed')) {
            $this->merge(['quantity_dispensed' => (float) $this->quantity_dispensed]);
        }
    }
}