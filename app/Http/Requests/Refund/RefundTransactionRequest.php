<?php

namespace App\Http\Requests\Refund;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // You can add authorization logic here
        // For example: return $this->user()->can('process-refund');
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                Rule::in([
                    'billing_error',
                    'service_not_rendered',
                    'duplicate_charge',
                    'patient_request',
                    'insurance_denial',
                    'administrative_correction',
                    'pricing_error',
                    'cancelled_service',
                    'other'
                ])
            ],
            'reason_notes' => 'required_if:reason,other|string|max:500',

            // Line items - omit entirely for full refund
            'line_items' => 'nullable|array|min:1',
            'service_code' => 'nullable|string',
            'line_items.*.line_item_id' => [
                'required_with:line_items',
                'integer',            ],
            'line_items.*.refund_amount' => [
                'nullable',
                'numeric',
                'min:0',
                // Custom validation might be added later to ensure not exceeding original amount
            ],

            // Refund methods
            'refund_methods' => 'required|array|min:1',
            'refund_methods.*.type' => [
                'required',
                Rule::in(['cash', 'card', 'insurance', 'mobile', 'bank_transfer', 'cheque', 'other'])
            ],
            'refund_methods.*.amount' => 'required|numeric|min:0',
            'refund_methods.*.reference' => 'nullable|string|max:255',

            // Inventory
            'restore_inventory' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'The refund reason is required.',
            'reason.in' => 'The selected refund reason is invalid.',
            
            'reason_notes.required_if' => 'Please provide notes when reason is "other".',
            'reason_notes.max' => 'Reason notes cannot exceed 500 characters.',
            
            'line_items.min' => 'At least one line item must be selected for partial refund.',
            'line_items.*.line_item_id.required_with' => 'Line item ID is required for each refund item.',
            'line_items.*.line_item_id.exists' => 'Selected line item does not exist.',
            'line_items.*.refund_amount.numeric' => 'Refund amount must be a number.',
            'line_items.*.refund_amount.min' => 'Refund amount cannot be negative.',
            
            'refund_methods.required' => 'At least one refund method is required.',
            'refund_methods.min' => 'At least one refund method must be provided.',
            'refund_methods.*.type.required' => 'Refund method type is required.',
            'refund_methods.*.type.in' => 'Invalid refund method type selected.',
            'refund_methods.*.amount.required' => 'Refund amount is required.',
            'refund_methods.*.amount.numeric' => 'Refund amount must be a number.',
            'refund_methods.*.amount.min' => 'Refund amount cannot be negative.',
            
            'restore_inventory.boolean' => 'Restore inventory must be true or false.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean value for restore_inventory
        if ($this->has('restore_inventory')) {
            $this->merge([
                'restore_inventory' => filter_var($this->restore_inventory, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reason' => 'refund reason',
            'reason_notes' => 'reason notes',
            'line_items' => 'line items',
            'line_items.*.line_item_id' => 'line item ID',
            'line_items.*.refund_amount' => 'refund amount',
            'refund_methods' => 'refund methods',
            'refund_methods.*.type' => 'refund method type',
            'refund_methods.*.amount' => 'refund method amount',
            'refund_methods.*.reference' => 'reference',
            'restore_inventory' => 'restore inventory flag',
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // You can manipulate the validated data here if needed
        // For example, calculate total refund amount or format data
    }
}