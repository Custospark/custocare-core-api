<?php

namespace App\Http\Requests\InvoiceLineItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreInvoiceLineItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\InvoiceLineItem::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'billing_cycle_id' => 'required|integer|exists:billing_cycles,id',
            'visit_id' => 'required|integer',
            'service_version_id' => 'required|integer|exists:service_versions,id',
            'service_version_snapshot' => 'nullable|array',
            'service_code' => 'required|string|max:50',
            'service_description' => 'required|string|max:500',
            'quantity' => 'nullable|numeric|min:0.01|max:999999.99',
            'unit_of_measure' => 'nullable|string|max:50',
            'unit_price_at_time' => 'required|numeric|min:0|max:99999999.99',
            'line_total_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'applied_discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'adjustment_amount' => 'nullable|numeric|min:-99999999.99|max:99999999.99',
            'adjustment_reason' => 'nullable|string|max:1000',
            'net_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'department_id' => 'nullable|integer',
            'staff_performed_id' => 'nullable|integer|exists:staff,id',
            'service_performed_at' => 'required|date',
            'service_duration_minutes' => 'nullable|integer|min:1|max:1440',
            'diagnosis_codes' => 'nullable|array',
            'diagnosis_codes.*' => 'string|max:20',
            'medical_necessity_notes' => 'nullable|string|max:2000',
            'modifier_codes' => 'nullable|array',
            'modifier_codes.*' => 'string|max:10',
            'revenue_code' => 'nullable|string|max:20',
            'procedure_code' => 'nullable|string|max:20',
            'insurance_specific_codes' => 'nullable|array',
            'preauthorization_number' => 'nullable|string|max:100',
            'requires_review' => 'nullable|boolean',
            'coding_reviewed' => 'nullable|boolean',
            'reviewed_by_staff_id' => 'nullable|integer|exists:staff,id',
            'reviewed_at' => 'nullable|date',
            'line_item_status' => 'nullable|string|in:pending,approved,billed,paid,denied,adjusted,written_off',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_cycle_id.required' => 'Billing cycle is required',
            'billing_cycle_id.exists' => 'Selected billing cycle does not exist',
            'service_version_id.required' => 'Service version is required',
            'service_version_id.exists' => 'Selected service version does not exist',
            'service_code.required' => 'Service code is required',
            'service_description.required' => 'Service description is required',
            'unit_price_at_time.required' => 'Unit price is required',
            'service_performed_at.required' => 'Service performed date is required',
            'service_performed_at.date' => 'Service performed date must be a valid date',
            'line_item_status.in' => 'Status must be one of: pending, approved, billed, paid, denied, adjusted, written_off',
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
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
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
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to create invoice line items',
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure numeric fields are properly formatted
        $numericFields = [
            'quantity',
            'unit_price_at_time',
            'line_total_amount',
            'applied_discount_percentage',
            'discount_amount',
            'adjustment_amount',
            'net_amount',
        ];

        foreach ($numericFields as $field) {
            if ($this->has($field) && is_numeric($this->$field)) {
                $this->merge([$field => (float) $this->$field]);
            }
        }

        // Ensure boolean fields are properly cast
        $booleanFields = ['requires_review', 'coding_reviewed'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        // Set default values if not provided
        if (!$this->has('quantity')) {
            $this->merge(['quantity' => 1.00]);
        }

        if (!$this->has('unit_of_measure')) {
            $this->merge(['unit_of_measure' => 'each']);
        }

        if (!$this->has('line_item_status')) {
            $this->merge(['line_item_status' => 'pending']);
        }

        // Set created_by_staff_id from authenticated user if not provided
        if (!$this->has('created_by_staff_id') && $this->user()) {
            $this->merge(['created_by_staff_id' => $this->user()->id]);
        }
    }
}