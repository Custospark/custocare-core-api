<?php

namespace App\Http\Requests\BillingCycle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateBillingCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $billingCycle = $this->route('billingCycle');
        return $this->user() && $this->user()->can('update', $billingCycle);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'sometimes|integer|exists:facilities,id',
            'visit_id' => 'sometimes|integer|exists:visits,id',
            'patient_id' => 'sometimes|integer|exists:patients,id',
            
            'cycle_type' => [
                'sometimes',
                'string',
                'in:visit_based,admission_discharge,daily_inpatient,weekly_inpatient,procedure_based,bundled_payment,subscription'
            ],
            
            'period_start' => 'sometimes|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            
            'total_amount_charged' => 'nullable|numeric|min:0|max:9999999999.99',
            'total_adjustments' => 'nullable|numeric|min:0|max:999999999.99',
            
            'primary_insurance_claim_number' => 'nullable|string|max:100',
            'insurance_covered_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'insurance_adjustment_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'insurance_payment_received' => 'nullable|numeric|min:0|max:999999999.99',
            'insurance_claim_submitted_at' => 'nullable|date',
            'insurance_payment_received_at' => 'nullable|date',
            
            'patient_responsibility_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'patient_copay_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'patient_deductible_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'patient_coinsurance_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'patient_payment_received' => 'nullable|numeric|min:0|max:999999999.99',
            
            'discount_applied' => 'nullable|numeric|min:0|max:999999999.99',
            'discount_reason' => 'nullable|string|max:200',
            'contractual_adjustment' => 'nullable|numeric|min:0|max:999999999.99',
            'charity_care_adjustment' => 'nullable|numeric|min:0|max:999999999.99',
            'bad_debt_adjustment' => 'nullable|numeric|min:0|max:999999999.99',
            
            'tax_details' => 'nullable|array',
            'total_tax_amount' => 'nullable|numeric|min:0|max:999999999.99',
            
            'billing_status' => [
                'sometimes',
                'string',
                'in:draft,pending_review,pending_submission,submitted_to_insurance,partially_paid,paid_in_full,payment_plan,collections,disputed,written_off,charity_care'
            ],
            
            'payment_due_date' => 'nullable|date',
            
            'collections_agency' => 'nullable|string|max:200',
            
            'is_disputed' => 'boolean',
            'dispute_reason' => 'nullable|string|max:1000',
            'dispute_opened_at' => 'nullable|date',
            'dispute_resolved_at' => 'nullable|date',
            
            'statement_count' => 'nullable|integer|min:0',
            'last_statement_sent_at' => 'nullable|date',
            'sent_to_collections_at' => 'nullable|date',
            
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'updated_by_staff_id' => 'nullable|integer|exists:staff,id',
            
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
            'facility_id.exists' => 'The selected facility does not exist',
            'visit_id.exists' => 'The selected visit does not exist',
            'patient_id.exists' => 'The selected patient does not exist',
            
            'cycle_type.in' => 'Invalid cycle type selected',
            
            'period_end.after_or_equal' => 'Period end date must be after or equal to period start date',
            
            'total_amount_charged.numeric' => 'Total amount charged must be a number',
            'total_amount_charged.min' => 'Total amount charged cannot be negative',
            
            'billing_status.in' => 'Invalid billing status selected',
            
            'created_by_staff_id.exists' => 'The selected staff member does not exist',
            'updated_by_staff_id.exists' => 'The selected staff member does not exist',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        $response = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
            'data' => []
        ];
        
        throw new HttpResponseException(
            response()->json($response, JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $billingCycle = $this->route('billingCycle');
        
        $response = [
            'success' => false,
            'message' => 'Unauthorized',
            'error' => 'You are not authorized to update this billing cycle',
            'data' => []
        ];
        
        throw new HttpResponseException(
            response()->json($response, JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for nullable fields
        $nullableFields = [
            'primary_insurance_claim_number',
            'discount_reason',
            'collections_agency',
            'dispute_reason',
        ];
        
        foreach ($nullableFields as $field) {
            if ($this->has($field) && trim($this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
        
        // Convert boolean strings to actual booleans
        if ($this->has('is_disputed')) {
            $this->merge([
                'is_disputed' => filter_var($this->input('is_disputed'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}