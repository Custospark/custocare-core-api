<?php

namespace App\Http\Requests\InventoryLedger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * Form request for storing a new inventory ledger entry.
 * Handles validation and authorization for creating ledger entries.
 */
class StoreInventoryLedgerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\InventoryLedger::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'inventory_item_id' => 'required|integer|exists:inventory_items,id',
            
            'transaction_type' => 'required|string|in:purchase,adjustment_increase,adjustment_decrease,consumption_visit,consumption_waste,return_to_supplier,transfer_in,transfer_out,cycle_count,expired,damaged,stolen,recalled',
            'quantity_change' => 'required|numeric|regex:/^-?\d+(\.\d{1,2})?$/',
            'balance_after_transaction' => 'sometimes|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'unit_of_measure' => 'required|string|max:50',
            
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'manufacture_date' => 'nullable|date|before_or_equal:today',
            
            'unit_cost_at_transaction' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            'total_cost' => 'nullable|numeric|regex:/^\d+(\.\d{1,2})?$/|min:0',
            
            'reference_visit_id' => 'nullable|integer|exists:visits,id',
            'reference_prescription_id' => 'nullable|integer|exists:prescriptions,id',
            'reference_purchase_order_id' => 'nullable|integer',
            'transfer_from_facility_id' => 'nullable|integer|exists:facilities,id',
            'transfer_to_facility_id' => 'nullable|integer|exists:facilities,id',
            
            'transaction_cause' => 'required|string|in:manual_entry,system_automated,physical_count,reconciliation,patient_use,procedural_use,administrative',
            'transaction_notes' => 'nullable|string|max:1000',
            'reference_document_number' => 'nullable|string|max:100',
            
            'performed_by_staff_id' => 'required|integer|exists:staff,id',
            'verified_by_staff_id' => 'nullable|integer|exists:staff,id',
            'verified_at' => 'nullable|date',
            
            'storage_location' => 'nullable|string|max:200',
            'department_id' => 'nullable|integer|exists:departments,id',
            
            'transaction_timestamp' => 'required|date',
            'transaction_uuid' => 'nullable|uuid',
            'transaction_hash' => 'nullable|string|max:128',
            'metadata' => 'nullable|json',
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
            'facility_id.required' => 'The facility is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
            
            'inventory_item_id.required' => 'The inventory item is required.',
            'inventory_item_id.exists' => 'The selected inventory item does not exist.',
            
            'transaction_type.required' => 'The transaction type is required.',
            'transaction_type.in' => 'The selected transaction type is invalid.',
            
            'quantity_change.required' => 'The quantity change is required.',
            'quantity_change.numeric' => 'The quantity change must be a number.',
            'quantity_change.regex' => 'The quantity change must have up to 2 decimal places.',
            
            'unit_of_measure.required' => 'The unit of measure is required.',
            'unit_of_measure.max' => 'The unit of measure may not be greater than 50 characters.',
            
            'expiry_date.after_or_equal' => 'The expiry date must be today or in the future.',
            'manufacture_date.before_or_equal' => 'The manufacture date cannot be in the future.',
            
            'unit_cost_at_transaction.numeric' => 'The unit cost must be a number.',
            'unit_cost_at_transaction.min' => 'The unit cost must be at least 0.',
            
            'performed_by_staff_id.required' => 'The staff member who performed the transaction is required.',
            'performed_by_staff_id.exists' => 'The selected staff member does not exist.',
            
            'transaction_timestamp.required' => 'The transaction timestamp is required.',
            'transaction_timestamp.date' => 'The transaction timestamp must be a valid date.',
            
            'transaction_uuid.uuid' => 'The transaction UUID must be a valid UUID.',
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
            'facility_id' => 'facility',
            'inventory_item_id' => 'inventory item',
            'transaction_type' => 'transaction type',
            'quantity_change' => 'quantity change',
            'unit_of_measure' => 'unit of measure',
            'performed_by_staff_id' => 'performed by staff',
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
            'message' => 'You are not authorized to create inventory ledger entries.',
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
        // Ensure quantity_change is properly formatted
        if ($this->has('quantity_change')) {
            $this->merge([
                'quantity_change' => (float) $this->quantity_change,
            ]);
        }
        
        // Set default transaction timestamp if not provided
        if (!$this->has('transaction_timestamp')) {
            $this->merge([
                'transaction_timestamp' => now()->toDateTimeString(),
            ]);
        }
        
        // Generate UUID if not provided
        if (!$this->has('transaction_uuid')) {
            $this->merge([
                'transaction_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        }
    }
}