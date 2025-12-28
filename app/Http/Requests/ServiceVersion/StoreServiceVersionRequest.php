<?php

namespace App\Http\Requests\ServiceVersion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * StoreServiceVersionRequest
 * 
 * Handles validation and authorization for creating new service versions.
 * Returns API-friendly validation errors.
 */
class StoreServiceVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies, return true here
        // The actual authorization will be checked in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_catalog_id' => 'required|integer|exists:service_catalogs,id',
            'facility_id' => 'nullable|integer|exists:facilities,id',
            'version_number' => 'required|string|max:20',
            'valid_from' => 'required|date_format:Y-m-d',
            'valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:valid_from',
            'is_current' => 'boolean',
            'currency_code' => 'required|string|size:3',
            'base_price_amount' => 'required|numeric|min:0|max:9999999999.99',
            'facility_markup_percentage' => 'nullable|numeric|min:-100|max:1000',
            'final_price_amount' => 'nullable|numeric|min:0|max:9999999999.99',
            'insurance_coverage_rates' => 'nullable|array',
            'insurance_coverage_rates.*' => 'numeric|min:0|max:100',
            'requires_preauthorization' => 'boolean',
            'preauthorization_criteria' => 'nullable|array',
            'preauth_processing_days' => 'nullable|integer|min:0|max:365',
            'is_billable' => 'boolean',
            'billing_method' => 'required|in:per_service,per_unit,per_hour,per_day,flat_fee,bundled,not_separately_billable',
            'minimum_billable_units' => 'required|numeric|min:0|max:999999.99',
            'maximum_billable_units' => 'nullable|numeric|min:0|max:999999.99|gte:minimum_billable_units',
            'bundled_service_ids' => 'nullable|array',
            'bundled_service_ids.*' => 'integer|exists:service_catalogs,id',
            'allowed_modifiers' => 'nullable|array',
            'modifier_price_adjustments' => 'nullable|array',
            'documentation_requirements' => 'nullable|string',
            'medical_necessity_criteria' => 'nullable|string',
            'required_diagnosis_codes' => 'nullable|array',
            'required_diagnosis_codes.*' => 'string|max:10',
            'direct_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'indirect_cost' => 'nullable|numeric|min:0|max:99999999.99',
            'target_margin_percentage' => 'nullable|numeric|min:-100|max:1000',
            'version_snapshot' => 'required|array',
            'change_notes' => 'nullable|string',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_catalog_id.required' => 'The service catalog ID is required.',
            'service_catalog_id.exists' => 'The selected service catalog does not exist.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'version_number.required' => 'The version number is required.',
            'version_number.max' => 'The version number may not be greater than 20 characters.',
            'valid_from.required' => 'The valid from date is required.',
            'valid_from.date_format' => 'The valid from date must be in the format YYYY-MM-DD.',
            'valid_to.date_format' => 'The valid to date must be in the format YYYY-MM-DD.',
            'valid_to.after_or_equal' => 'The valid to date must be after or equal to the valid from date.',
            'currency_code.required' => 'The currency code is required.',
            'currency_code.size' => 'The currency code must be exactly 3 characters.',
            'base_price_amount.required' => 'The base price amount is required.',
            'base_price_amount.min' => 'The base price amount must be at least 0.',
            'base_price_amount.max' => 'The base price amount is too large.',
            'facility_markup_percentage.min' => 'The facility markup percentage cannot be less than -100.',
            'facility_markup_percentage.max' => 'The facility markup percentage cannot exceed 1000.',
            'final_price_amount.min' => 'The final price amount must be at least 0.',
            'final_price_amount.max' => 'The final price amount is too large.',
            'insurance_coverage_rates.*.min' => 'Each insurance coverage rate must be at least 0%.',
            'insurance_coverage_rates.*.max' => 'Each insurance coverage rate cannot exceed 100%.',
            'preauth_processing_days.min' => 'Preauthorization processing days must be at least 0.',
            'preauth_processing_days.max' => 'Preauthorization processing days cannot exceed 365.',
            'billing_method.required' => 'The billing method is required.',
            'billing_method.in' => 'The selected billing method is invalid.',
            'minimum_billable_units.required' => 'The minimum billable units is required.',
            'minimum_billable_units.min' => 'The minimum billable units must be at least 0.',
            'minimum_billable_units.max' => 'The minimum billable units is too large.',
            'maximum_billable_units.min' => 'The maximum billable units must be at least 0.',
            'maximum_billable_units.max' => 'The maximum billable units is too large.',
            'maximum_billable_units.gte' => 'The maximum billable units must be greater than or equal to minimum billable units.',
            'bundled_service_ids.*.exists' => 'One or more bundled service IDs do not exist.',
            'required_diagnosis_codes.*.max' => 'Each diagnosis code may not be greater than 10 characters.',
            'direct_cost.min' => 'The direct cost must be at least 0.',
            'direct_cost.max' => 'The direct cost is too large.',
            'indirect_cost.min' => 'The indirect cost must be at least 0.',
            'indirect_cost.max' => 'The indirect cost is too large.',
            'target_margin_percentage.min' => 'The target margin percentage cannot be less than -100.',
            'target_margin_percentage.max' => 'The target margin percentage cannot exceed 1000.',
            'version_snapshot.required' => 'The version snapshot is required.',
            'version_snapshot.array' => 'The version snapshot must be an array.',
            'created_by_staff_id.exists' => 'The selected staff member does not exist.',
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
        $errors = $validator->errors();
        
        Log::warning('Service version store validation failed', [
            'errors' => $errors->toArray(),
            'input' => $this->all()
        ]);
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors->toArray(),
                'status' => 422
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Ensure numeric fields are properly cast
        $this->merge([
            'service_catalog_id' => (int) $this->service_catalog_id,
            'facility_id' => $this->has('facility_id') ? (int) $this->facility_id : null,
            'base_price_amount' => (float) $this->base_price_amount,
            'facility_markup_percentage' => $this->has('facility_markup_percentage') 
                ? (float) $this->facility_markup_percentage 
                : null,
            'final_price_amount' => $this->has('final_price_amount') 
                ? (float) $this->final_price_amount 
                : null,
            'preauth_processing_days' => $this->has('preauth_processing_days') 
                ? (int) $this->preauth_processing_days 
                : null,
            'minimum_billable_units' => (float) $this->minimum_billable_units,
            'maximum_billable_units' => $this->has('maximum_billable_units') 
                ? (float) $this->maximum_billable_units 
                : null,
            'direct_cost' => $this->has('direct_cost') ? (float) $this->direct_cost : null,
            'indirect_cost' => $this->has('indirect_cost') ? (float) $this->indirect_cost : null,
            'target_margin_percentage' => $this->has('target_margin_percentage') 
                ? (float) $this->target_margin_percentage 
                : null,
            'created_by_staff_id' => $this->has('created_by_staff_id') 
                ? (int) $this->created_by_staff_id 
                : null,
        ]);
    }

    /**
     * Get validated data with additional processing.
     *
     * @return array
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Set default values if not provided
        $validated['is_current'] = $validated['is_current'] ?? false;
        $validated['requires_preauthorization'] = $validated['requires_preauthorization'] ?? false;
        $validated['is_billable'] = $validated['is_billable'] ?? true;
        $validated['currency_code'] = strtoupper($validated['currency_code']);
        
        return $validated;
    }
}