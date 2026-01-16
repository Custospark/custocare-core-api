<?php

namespace App\Http\Requests\InventoryItem;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateInventoryItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $inventoryItem = $this->route('inventory_item');
        return $this->user()->can('update', $inventoryItem);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $inventoryItem = $this->route('inventory_item');
        
        return [
            'item_code' => 'sometimes|required|string|max:100|unique:inventory_items,item_code,' . $inventoryItem->id,
            'item_name' => 'sometimes|required|string|max:300',
            'item_description' => 'nullable|string',
            'facility_id' => 'sometimes|integer|exists:facilities,id',

            
            'item_category' => 'sometimes|required|in:medication,medical_supply,surgical_instrument,diagnostic_equipment,implantable_device,prosthetic,laboratory_reagent,personal_protective_equipment,administrative_supply,other',
            'item_subcategory' => 'nullable|string|max:100',
            
            'generic_name' => 'nullable|string|max:300',
            'brand_name' => 'nullable|string|max:300',
            'ndc_code' => 'nullable|string|max:20|unique:inventory_items,ndc_code,' . $inventoryItem->id,
            'drug_class' => 'nullable|string|max:100',
            'controlled_substance_schedule' => 'nullable|in:I,II,III,IV,V,non_controlled',
            'active_ingredients' => 'nullable|json',
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100',
            'route_of_administration' => 'nullable|string|max:100',
            
            'manufacturer' => 'nullable|string|max:200',
            'manufacturer_item_number' => 'nullable|string|max:100',
            'supplier' => 'nullable|string|max:200',
            
            'unit_of_measure' => 'sometimes|required|string|max:50',
            'package_quantity' => 'sometimes|required|integer|min:1',
            'packaging_type' => 'nullable|string|max:100',
            
            'unit_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'average_wholesale_price' => 'nullable|numeric|min:0|max:99999999.99',
            'currency_code' => 'sometimes|required|string|size:3',
            
            'storage_requirements' => 'nullable|json',
            'requires_refrigeration' => 'sometimes|boolean',
            'requires_controlled_access' => 'sometimes|boolean',
            'storage_location_type' => 'nullable|string|max:100',
            
            'requires_prescription' => 'sometimes|boolean',
            'regulatory_approvals' => 'nullable|json',
            'fda_approval_number' => 'nullable|string|max:100',
            
            'is_hazardous' => 'sometimes|boolean',
            'safety_warnings' => 'nullable|json',
            'contraindications' => 'nullable|json',
            'special_handling_instructions' => 'nullable|string',
            
            'is_billable' => 'sometimes|boolean',
            'track_by_lot' => 'sometimes|boolean',
            'track_by_serial' => 'sometimes|boolean',
            'reorder_point' => 'nullable|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:1',
            'safety_stock_level' => 'nullable|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            
            'status' => 'sometimes|required|in:active,inactive,discontinued,recalled',
            
            'metadata' => 'nullable|json',
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
            'item_code.required' => 'Item code is required.',
            'item_code.unique' => 'This item code is already in use.',
            'item_name.required' => 'Item name is required.',
            'item_category.required' => 'Item category is required.',
            'item_category.in' => 'Invalid item category selected.',
            'ndc_code.unique' => 'This NDC code is already registered.',
            'unit_of_measure.required' => 'Unit of measure is required.',
            'package_quantity.required' => 'Package quantity is required.',
            'currency_code.required' => 'Currency code is required.',
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected.',
            'facility_id.required' => 'Facility ID is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
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
        $response = response()->json([
            'success' => false,
            'message' => 'Validation errors occurred',
            'errors' => $validator->errors(),
            'error_code' => 'VALIDATION_FAILED'
        ], 422);

        throw new HttpResponseException($response);
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
        $response = response()->json([
            'success' => false,
            'message' => 'You are not authorized to update this inventory item',
            'error_code' => 'UNAUTHORIZED_ACTION'
        ], 403);

        throw new HttpResponseException($response);
    }
}