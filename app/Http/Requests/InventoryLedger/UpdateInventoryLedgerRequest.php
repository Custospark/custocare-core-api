<?php

namespace App\Http\Requests\InventoryLedger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * Form request for updating an existing inventory ledger entry.
 * Note: Ledger entries are typically immutable - use only for corrections.
 */
class UpdateInventoryLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $inventoryLedger = $this->route('inventory_ledger');
        return $this->user() && $this->user()->can('update', $inventoryLedger);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_notes' => 'nullable|string|max:1000',
            'reference_document_number' => 'nullable|string|max:100',
            'verified_by_staff_id' => 'nullable|integer|exists:staff,id',
            'verified_at' => 'nullable|date',
            'storage_location' => 'nullable|string|max:200',
            'department_id' => 'nullable|integer|exists:departments,id',
            'metadata' => 'nullable|json',
            
            // These fields are typically immutable but can be updated for corrections
            'quantity_change' => 'sometimes|numeric|regex:/^-?\d+(\.\d{1,2})?$/',
            'unit_cost_at_transaction' => 'sometimes|nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'total_cost' => 'sometimes|nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'lot_number' => 'sometimes|nullable|string|max:100',
            'serial_number' => 'sometimes|nullable|string|max:100',
            'expiry_date' => 'sometimes|nullable|date|after_or_equal:today',
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
            'quantity_change.numeric' => 'The quantity change must be a number.',
            'quantity_change.regex' => 'The quantity change must have up to 2 decimal places.',
            
            'unit_cost_at_transaction.numeric' => 'The unit cost must be a number.',
            'unit_cost_at_transaction.min' => 'The unit cost must be at least 0.',
            
            'verified_by_staff_id.exists' => 'The selected staff member does not exist.',
            
            'expiry_date.after_or_equal' => 'The expiry date must be today or in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'transaction_notes' => 'transaction notes',
            'verified_by_staff_id' => 'verified by staff',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Validation errors occurred.',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        $response = response()->json([
            'success' => false,
            'message' => 'You are not authorized to update this inventory ledger entry.',
        ], 403);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure numeric fields are properly formatted
        $numericFields = ['quantity_change', 'unit_cost_at_transaction', 'total_cost'];
        
        foreach ($numericFields as $field) {
            if ($this->has($field) && $this->$field !== null) {
                $this->merge([
                    $field => (float) $this->$field,
                ]);
            }
        }
        
        // Clear verified fields if unverifying
        if ($this->has('verified_at') && empty($this->verified_at)) {
            $this->merge([
                'verified_by_staff_id' => null,
                'verified_at' => null,
            ]);
        }
    }
}