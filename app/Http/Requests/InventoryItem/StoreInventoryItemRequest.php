<?php

namespace App\Http\Requests\InventoryItem;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreInventoryItemRequest extends FormRequest
{
    /**
     * The facility ID from request headers.
     *
     * @var int|null
     */
    protected ?int $facilityId = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        try {
            // Get facility ID from headers
            $this->facilityId = $this->header('X-Facility-Id');
            
            if (!$this->facilityId) {
                Log::warning('Facility ID missing in request headers for inventory item creation');
                return false;
            }

            // Check if user has permission to create inventory items for this facility
            // Example: return $this->user()->can('create', [InventoryItem::class, $this->facilityId]);
            return true;

        } catch (\Exception $e) {
            Log::error('Authorization failed for inventory item creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $facilityId = $this->header('X-Facility-Id');
        
        // Base rules for item code uniqueness within facility
        $itemCodeRule = [
            'nullable',
            'string',
            'max:100'
        ];

        // Add unique constraint within facility if facility ID is provided
        if ($facilityId) {
            $itemCodeRule[] = 'unique:inventory_items,item_code,NULL,item_uuid,facility_id,' . $facilityId;
        }

        // Base rules for NDC code uniqueness within facility
        $ndcCodeRule = [
            'nullable',
            'string',
            'max:20'
        ];

        // Add unique constraint within facility if facility ID is provided
        if ($facilityId) {
            $ndcCodeRule[] = 'unique:inventory_items,ndc_code,NULL,item_uuid,facility_id,' . $facilityId . ',ndc_code,!NULL';
        }

        return [
            'item_code' => $itemCodeRule,
            'item_name' => [
                'required',
                'string',
                'max:300'
            ],
            'item_description' => [
                'nullable',
                'string'
            ],
            'item_category' => [
                'required',
                'string',
                'in:medication,medical_supply,surgical_instrument,diagnostic_equipment,implantable_device,prosthetic,laboratory_reagent,personal_protective_equipment,administrative_supply,other'
            ],
            'item_subcategory' => [
                'nullable',
                'string',
                'max:100'
            ],
            'generic_name' => [
                'nullable',
                'string',
                'max:300'
            ],
            'brand_name' => [
                'nullable',
                'string',
                'max:300'
            ],
            'ndc_code' => $ndcCodeRule,
            'drug_class' => [
                'nullable',
                'string',
                'max:100'
            ],
            'controlled_substance_schedule' => [
                'nullable',
                'string',
                'in:I,II,III,IV,V,non_controlled'
            ],
            'active_ingredients' => [
                'nullable',
                'array'
            ],
            'active_ingredients.*' => [
                'string',
                'max:200'
            ],
            'dosage_form' => [
                'nullable',
                'string',
                'max:100'
            ],
            'strength' => [
                'nullable',
                'string',
                'max:100'
            ],
            'route_of_administration' => [
                'nullable',
                'string',
                'max:100'
            ],
            'manufacturer' => [
                'nullable',
                'string',
                'max:200'
            ],
            'manufacturer_item_number' => [
                'nullable',
                'string',
                'max:100'
            ],
            'supplier' => [
                'nullable',
                'string',
                'max:200'
            ],
            'unit_of_measure' => [
                'required',
                'string',
                'max:50'
            ],
            'package_quantity' => [
                'required',
                'integer',
                'min:1',
                'max:65535'
            ],
            'packaging_type' => [
                'nullable',
                'string',
                'max:100'
            ],
            'unit_cost' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99'
            ],
            'average_wholesale_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99'
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha'
            ],
            'storage_requirements' => [
                'nullable',
                'array'
            ],
            'requires_refrigeration' => [
                'boolean'
            ],
            'requires_controlled_access' => [
                'boolean'
            ],
            'storage_location_type' => [
                'nullable',
                'string',
                'max:100'
            ],
            'requires_prescription' => [
                'boolean'
            ],
            'regulatory_approvals' => [
                'nullable',
                'array'
            ],
            'fda_approval_number' => [
                'nullable',
                'string',
                'max:100'
            ],
            'is_hazardous' => [
                'boolean'
            ],
            'safety_warnings' => [
                'nullable',
                'array'
            ],
            'safety_warnings.*' => [
                'string',
                'max:500'
            ],
            'contraindications' => [
                'nullable',
                'array'
            ],
            'contraindications.*' => [
                'string',
                'max:500'
            ],
            'special_handling_instructions' => [
                'nullable',
                'string'
            ],
            'is_billable' => [
                'boolean'
            ],
            'track_by_lot' => [
                'boolean'
            ],
            'track_by_serial' => [
                'boolean'
            ],
            'reorder_point' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535'
            ],
            'reorder_quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535'
            ],
            'safety_stock_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535'
            ],
            'max_stock_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535'
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive,discontinued,recalled'
            ],
            'metadata' => [
                'nullable',
                'array'
            ],
            'facility_id' => [
                'prohibited' // Prevent setting facility_id through API
            ],
            'item_uuid' => [
                'prohibited' // Prevent setting item_uuid through API
            ],
            'created_by_staff_id' => [
                'prohibited' // Prevent setting created_by_staff_id through API
            ]
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
            'item_code.unique' => 'This item code already exists in your facility. Please use a different code.',
            'item_code.max' => 'The item code must not exceed 100 characters.',
            'item_name.required' => 'The item name is required.',
            'item_name.max' => 'The item name must not exceed 300 characters.',
            'item_category.required' => 'The item category is required.',
            'item_category.in' => 'The selected item category is invalid.',
            'ndc_code.unique' => 'This NDC code already exists in your facility. Please use a different code.',
            'ndc_code.max' => 'The NDC code must not exceed 20 characters.',
            'unit_of_measure.required' => 'The unit of measure is required.',
            'unit_of_measure.max' => 'The unit of measure must not exceed 50 characters.',
            'package_quantity.required' => 'The package quantity is required.',
            'package_quantity.min' => 'The package quantity must be at least 1.',
            'package_quantity.max' => 'The package quantity must not exceed 65535.',
            'unit_cost.numeric' => 'The unit cost must be a valid number.',
            'unit_cost.min' => 'The unit cost cannot be negative.',
            'unit_cost.max' => 'The unit cost is too large.',
            'average_wholesale_price.numeric' => 'The average wholesale price must be a valid number.',
            'average_wholesale_price.min' => 'The average wholesale price cannot be negative.',
            'average_wholesale_price.max' => 'The average wholesale price is too large.',
            'currency_code.required' => 'The currency code is required.',
            'currency_code.size' => 'The currency code must be exactly 3 characters.',
            'currency_code.alpha' => 'The currency code must contain only letters.',
            'status.required' => 'The status is required.',
            'status.in' => 'The selected status is invalid.',
            'facility_id.prohibited' => 'Facility ID cannot be set through this endpoint.',
            'item_uuid.prohibited' => 'Item UUID cannot be set.',
            'created_by_staff_id.prohibited' => 'Created by staff ID cannot be set.',
            'reorder_point.min' => 'The reorder point cannot be negative.',
            'reorder_point.max' => 'The reorder point must not exceed 65535.',
            'reorder_quantity.min' => 'The reorder quantity must be at least 1.',
            'reorder_quantity.max' => 'The reorder quantity must not exceed 65535.',
            'safety_stock_level.min' => 'The safety stock level cannot be negative.',
            'safety_stock_level.max' => 'The safety stock level must not exceed 65535.',
            'max_stock_level.min' => 'The max stock level cannot be negative.',
            'max_stock_level.max' => 'The max stock level must not exceed 65535.'
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
            'item_code' => 'item code',
            'item_name' => 'item name',
            'item_category' => 'item category',
            'item_subcategory' => 'item subcategory',
            'generic_name' => 'generic name',
            'brand_name' => 'brand name',
            'ndc_code' => 'NDC code',
            'drug_class' => 'drug class',
            'controlled_substance_schedule' => 'controlled substance schedule',
            'active_ingredients' => 'active ingredients',
            'dosage_form' => 'dosage form',
            'strength' => 'strength',
            'route_of_administration' => 'route of administration',
            'manufacturer' => 'manufacturer',
            'manufacturer_item_number' => 'manufacturer item number',
            'supplier' => 'supplier',
            'unit_of_measure' => 'unit of measure',
            'package_quantity' => 'package quantity',
            'packaging_type' => 'packaging type',
            'unit_cost' => 'unit cost',
            'average_wholesale_price' => 'average wholesale price',
            'currency_code' => 'currency code',
            'storage_requirements' => 'storage requirements',
            'requires_refrigeration' => 'requires refrigeration',
            'requires_controlled_access' => 'requires controlled access',
            'storage_location_type' => 'storage location type',
            'requires_prescription' => 'requires prescription',
            'regulatory_approvals' => 'regulatory approvals',
            'fda_approval_number' => 'FDA approval number',
            'is_hazardous' => 'is hazardous',
            'safety_warnings' => 'safety warnings',
            'contraindications' => 'contraindications',
            'special_handling_instructions' => 'special handling instructions',
            'is_billable' => 'is billable',
            'track_by_lot' => 'track by lot',
            'track_by_serial' => 'track by serial',
            'reorder_point' => 'reorder point',
            'reorder_quantity' => 'reorder quantity',
            'safety_stock_level' => 'safety stock level',
            'max_stock_level' => 'max stock level',
            'status' => 'status',
            'metadata' => 'metadata',
            'facility_id' => 'facility ID',
            'item_uuid' => 'item UUID',
            'created_by_staff_id' => 'created by staff ID'
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        
        Log::warning('Inventory item creation validation failed', [
            'facility_id' => $this->facilityId,
            'errors' => $errors->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check your input.',
                'errors' => $errors,
                'data' => []
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        Log::warning('Unauthorized inventory item creation attempt', [
            'facility_id' => $this->facilityId,
            'user_id' => Auth::id()
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create inventory items in your facility.',
                'errors' => [],
                'data' => []
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
        $booleanFields = [
            'requires_refrigeration',
            'requires_controlled_access',
            'requires_prescription',
            'is_hazardous',
            'is_billable',
            'track_by_lot',
            'track_by_serial'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        // Parse JSON strings to arrays if provided as strings
        $jsonFields = [
            'active_ingredients',
            'storage_requirements',
            'regulatory_approvals',
            'safety_warnings',
            'contraindications',
            'metadata'
        ];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        // Clean and format numeric fields
        $numericFields = [
            'unit_cost',
            'average_wholesale_price',
            'package_quantity',
            'reorder_point',
            'reorder_quantity',
            'safety_stock_level',
            'max_stock_level'
        ];

        foreach ($numericFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    // Remove any non-numeric characters except decimal point
                    $value = preg_replace('/[^0-9.]/', '', $value);
                    if ($value !== '') {
                        $this->merge([$field => (float) $value]);
                    } else {
                        $this->merge([$field => null]);
                    }
                }
            }
        }

        // Trim string fields
        $stringFields = [
            'item_code',
            'item_name',
            'item_description',
            'item_subcategory',
            'generic_name',
            'brand_name',
            'ndc_code',
            'drug_class',
            'dosage_form',
            'strength',
            'route_of_administration',
            'manufacturer',
            'manufacturer_item_number',
            'supplier',
            'unit_of_measure',
            'packaging_type',
            'currency_code',
            'storage_location_type',
            'fda_approval_number',
            'special_handling_instructions'
        ];

        foreach ($stringFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        // Convert to uppercase for currency code
        if ($this->has('currency_code')) {
            $this->merge(['currency_code' => strtoupper(trim($this->input('currency_code')))]);
        }

        // Set default values if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => 'active']);
        }

        if (!$this->has('currency_code')) {
            $this->merge(['currency_code' => 'USD']);
        }

        if (!$this->has('unit_of_measure')) {
            $this->merge(['unit_of_measure' => 'each']);
        }

        if (!$this->has('package_quantity')) {
            $this->merge(['package_quantity' => 1]);
        }

        // Generate item UUID if not provided (will be generated by service, but prepare here for validation)
        // if (!$this->has('item_uuid')) {
        //     $this->merge(['item_uuid' => \Illuminate\Support\Str::uuid()->toString()]);
        // }
    }

    /**
     * Get the validated data from the request.
     * Override to add facility context.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Always include facility context in logs
        if ($this->facilityId) {
            Log::info('Inventory item creation validation passed', [
                'facility_id' => $this->facilityId,
                'validated_fields' => array_keys($validated)
            ]);
        }

        return $validated;
    }
}