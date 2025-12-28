<?php

namespace App\Http\Requests\DataResidencyRule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\DataResidencyRule;

class UpdateDataResidencyRuleRequest extends FormRequest
{
    /**
     * The data residency rule being updated.
     *
     * @var DataResidencyRule|null
     */
    protected ?DataResidencyRule $rule = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $this->rule = DataResidencyRule::find($this->route('dataResidencyRule'));
        
        if (!$this->rule) {
            return false;
        }
        
        return $this->user()->can('update', $this->rule);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ruleId = $this->route('dataResidencyRule');
        
        return [
            'region_code' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z]{2}(-[A-Z0-9]{2,7})?$/',
                function ($attribute, $value, $fail) use ($ruleId) {
                    $exists = DataResidencyRule::where('region_code', $value)
                        ->when($ruleId, function ($query) use ($ruleId) {
                            return $query->where('id', '!=', $ruleId);
                        })
                        ->exists();
                    
                    if ($exists) {
                        $fail('This region code is already in use.');
                    }
                }
            ],
            'region_name' => 'sometimes|required|string|max:200',
            'data_category' => [
                'sometimes',
                'required',
                'string',
                'in:clinical_records,financial_data,identity_information,audit_logs,research_data,genomic_data',
                function ($attribute, $value, $fail) use ($ruleId) {
                    if ($this->has('region_code')) {
                        $regionCode = $this->input('region_code');
                    } else {
                        $regionCode = $this->rule?->region_code;
                    }
                    
                    if ($regionCode) {
                        $exists = DataResidencyRule::where('region_code', $regionCode)
                            ->where('data_category', $value)
                            ->when($ruleId, function ($query) use ($ruleId) {
                                return $query->where('id', '!=', $ruleId);
                            })
                            ->exists();
                        
                        if ($exists) {
                            $fail('A rule already exists for this region and data category combination.');
                        }
                    }
                }
            ],
            
            // Geographic restrictions
            'allowed_storage_regions' => 'sometimes|required|array',
            'allowed_storage_regions.*' => 'string|max:10',
            'allowed_processing_regions' => 'sometimes|required|array',
            'allowed_processing_regions.*' => 'string|max:10',
            'allowed_backup_regions' => 'sometimes|required|array',
            'allowed_backup_regions.*' => 'string|max:10',
            'prohibited_regions' => 'nullable|array',
            'prohibited_regions.*' => 'string|max:10',
            
            // Encryption requirements
            'encryption_requirements' => 'sometimes|required|array',
            'encryption_requirements.algorithm' => 'required_with:encryption_requirements|string',
            'encryption_requirements.key_length' => 'required_with:encryption_requirements|integer|min:128',
            'encryption_at_rest_required' => 'sometimes|boolean',
            'encryption_in_transit_required' => 'sometimes|boolean',
            'encryption_in_use_required' => 'sometimes|boolean',
            
            // Access controls
            'cross_border_transfer_approval_required' => 'sometimes|boolean',
            'approval_authority' => 'nullable|array',
            'transfer_mechanisms' => 'nullable|array',
            
            // Retention policies
            'minimum_retention_period_years' => 'sometimes|required|integer|min:1|max:100',
            'maximum_retention_period_years' => 'nullable|integer|min:1|max:100',
            'retention_basis' => 'sometimes|required|string|in:legal_requirement,business_need,consent_based',
            
            // Deletion requirements
            'right_to_erasure_applicable' => 'sometimes|boolean',
            'erasure_exceptions' => 'nullable|array',
            'erasure_response_time_days' => 'sometimes|required|integer|min:1|max:365',
            
            // Breach notification
            'breach_notification_hours' => 'sometimes|required|integer|min:1|max:720',
            'notification_authorities' => 'nullable|array',
            
            // Applicable laws
            'applicable_regulations' => 'sometimes|required|array',
            'applicable_regulations.*' => 'string',
            'regulation_summary' => 'nullable|string',
            'legal_reference_url' => 'nullable|url|max:512',
            
            // Status
            'status' => 'sometimes|required|string|in:active,under_review,superseded',
            'effective_from' => 'sometimes|required|date',
            'effective_to' => 'nullable|date',
            
            // Audit
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array'
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('maximum_retention_period_years') && $this->has('minimum_retention_period_years')) {
                $min = $this->input('minimum_retention_period_years');
                $max = $this->input('maximum_retention_period_years');
                
                if ($max && $min > $max) {
                    $validator->errors()->add(
                        'maximum_retention_period_years',
                        'Maximum retention period must be greater than or equal to minimum retention period'
                    );
                }
            }
            
            if ($this->has('effective_from') && $this->has('effective_to')) {
                $from = $this->input('effective_from');
                $to = $this->input('effective_to');
                
                if ($to && $from > $to) {
                    $validator->errors()->add(
                        'effective_to',
                        'Effective to date must be after effective from date'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'region_code.regex' => 'Region code must be in format: XX or XX-XXXXX (uppercase letters and numbers)',
            'data_category.in' => 'Invalid data category. Valid options are: ' . 
                implode(', ', array_keys(DataResidencyRule::DATA_CATEGORIES)),
            'encryption_requirements.required_with' => 'Encryption requirements must include algorithm and key length',
            'maximum_retention_period_years.gte' => 'Maximum retention period must be greater than or equal to minimum retention period',
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
            'region_code' => 'region code',
            'region_name' => 'region name',
            'data_category' => 'data category',
            'allowed_storage_regions' => 'allowed storage regions',
            'allowed_processing_regions' => 'allowed processing regions',
            'allowed_backup_regions' => 'allowed backup regions',
            'encryption_requirements.algorithm' => 'encryption algorithm',
            'encryption_requirements.key_length' => 'encryption key length',
            'minimum_retention_period_years' => 'minimum retention period',
            'maximum_retention_period_years' => 'maximum retention period',
            'breach_notification_hours' => 'breach notification hours',
            'effective_from' => 'effective from date',
            'effective_to' => 'effective to date',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert JSON strings to arrays if needed
        $jsonFields = [
            'allowed_storage_regions',
            'allowed_processing_regions',
            'allowed_backup_regions',
            'prohibited_regions',
            'encryption_requirements',
            'approval_authority',
            'transfer_mechanisms',
            'erasure_exceptions',
            'notification_authorities',
            'applicable_regulations',
            'metadata'
        ];
        
        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $decoded = json_decode($this->$field, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
        
        // Ensure arrays are properly formatted
        $arrayFields = [
            'allowed_storage_regions',
            'allowed_processing_regions',
            'allowed_backup_regions',
            'prohibited_regions',
            'applicable_regulations'
        ];
        
        foreach ($arrayFields as $field) {
            if ($this->has($field) && !is_array($this->$field)) {
                $this->merge([$field => []]);
            }
        }
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
        
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors->messages(),
            'error_code' => 'VALIDATION_ERROR'
        ], 422);
        
        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this data residency rule',
            'error_code' => 'UNAUTHORIZED'
        ], 403);
        
        throw new HttpResponseException($response);
    }
}