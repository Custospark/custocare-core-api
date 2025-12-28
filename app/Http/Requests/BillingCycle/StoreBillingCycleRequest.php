<?php

namespace App\Http\Requests\BillingCycle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreBillingCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\BillingCycle::class);
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
            'visit_id' => 'required|integer|exists:visits,id',
            'patient_id' => 'required|integer|exists:patients,id',
            
            'cycle_type' => [
                'required',
                'string',
                'in:visit_based,admission_discharge,daily_inpatient,weekly_inpatient,procedure_based,bundled_payment,subscription'
            ],
            
            'period_start' => 'required|date',
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
                'nullable',
                'string',
                'in:draft,pending_review,pending_submission,submitted_to_insurance,partially_paid,paid_in_full,payment_plan,collections,disputed,written_off,charity_care'
            ],
            
            'payment_due_date' => 'nullable|date',
            
            'collections_agency' => 'nullable|string|max:200',
            
            'dispute_reason' => 'nullable|string|max:1000',
            
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
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The selected visit does not exist',
            
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'The selected patient does not exist',
            
            'cycle_type.required' => 'Cycle type is required',
            'cycle_type.in' => 'Invalid cycle type selected',
            
            'period_start.required' => 'Period start date is required',
            'period_end.after_or_equal' => 'Period end date must be after or equal to period start date',
            
            'total_amount_charged.numeric' => 'Total amount charged must be a number',
            'total_amount_charged.min' => 'Total amount charged cannot be negative',
            
            'billing_status.in' => 'Invalid billing status selected',
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
        $response = [
            'success' => false,
            'message' => 'Unauthorized',
            'error' => 'You are not authorized to create billing cycles',
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
        // Generate UUID if not provided
        if (!$this->has('billing_cycle_uuid')) {
            $this->merge([
                'billing_cycle_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        }
        
        // Set default billing status if not provided
        if (!$this->has('billing_status')) {
            $this->merge([
                'billing_status' => 'draft',
            ]);
        }
        
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
    }
}