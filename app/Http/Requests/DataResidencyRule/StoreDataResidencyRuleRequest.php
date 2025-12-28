<?php

namespace App\Http\Requests\DataResidencyRule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class StoreDataResidencyRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\DataResidencyRule::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'region_code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Z]{2}(-[A-Z0-9]{2,7})?$/'
            ],
            'region_name' => 'required|string|max:200',
            'data_category' => [
                'required',
                'string',
                'in:clinical_records,financial_data,identity_information,audit_logs,research_data,genomic_data'
            ],
            
            // Geographic restrictions
            'allowed_storage_regions' => 'required|array',
            'allowed_storage_regions.*' => 'string|max:10',
            'allowed_processing_regions' => 'required|array',
            'allowed_processing_regions.*' => 'string|max:10',
            'allowed_backup_regions' => 'required|array',
            'allowed_backup_regions.*' => 'string|max:10',
            'prohibited_regions' => 'nullable|array',
            'prohibited_regions.*' => 'string|max:10',
            
            // Encryption requirements
            'encryption_requirements' => 'required|array',
            'encryption_requirements.algorithm' => 'required|string',
            'encryption_requirements.key_length' => 'required|integer|min:128',
            'encryption_at_rest_required' => 'required|boolean',
            'encryption_in_transit_required' => 'required|boolean',
            'encryption_in_use_required' => 'required|boolean',
            
            // Access controls
            'cross_border_transfer_approval_required' => 'required|boolean',
            'approval_authority' => 'nullable|array',
            'transfer_mechanisms' => 'nullable|array',
            
            // Retention policies
            'minimum_retention_period_years' => 'required|integer|min:1|max:100',
            'maximum_retention_period_years' => 'nullable|integer|min:1|max:100|gte:minimum_retention_period_years',
            'retention_basis' => 'required|string|in:legal_requirement,business_need,consent_based',
            
            // Deletion requirements
            'right_to_erasure_applicable' => 'required|boolean',
            'erasure_exceptions' => 'nullable|array',
            'erasure_response_time_days' => 'required|integer|min:1|max:365',
            
            // Breach notification
            'breach_notification_hours' => 'required|integer|min:1|max:720',
            'notification_authorities' => 'nullable|array',
            
            // Applicable laws
            'applicable_regulations' => 'required|array',
            'applicable_regulations.*' => 'string',
            'regulation_summary' => 'nullable|string',
            'legal_reference_url' => 'nullable|url|max:512',
            
            // Status
            'status' => 'required|string|in:active,under_review,superseded',
            'effective_from' => 'required|date|after_or_equal:today',
            'effective_to' => 'nullable|date|after:effective_from',
            
            // Audit
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array'
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
            'region_code.regex' => 'Region code must be in format: XX or XX-XXXXX (uppercase letters and numbers)',
            'data_category.in' => 'Invalid data category. Valid options are: ' . 
                implode(', ', array_keys(\App\Models\DataResidencyRule::DATA_CATEGORIES)),
            'maximum_retention_period_years.gte' => 'Maximum retention period must be greater than or equal to minimum retention period',
            'effective_from.after_or_equal' => 'Effective from date cannot be in the past',
            'effective_to.after' => 'Effective to date must be after effective from date',
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
        
        // Set default values for JSON fields if not provided
        if (!$this->has('encryption_requirements')) {
            $this->merge([
                'encryption_requirements' => [
                    'algorithm' => 'AES-256',
                    'key_length' => 256
                ]
            ]);
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
            'message' => 'You are not authorized to create data residency rules',
            'error_code' => 'UNAUTHORIZED'
        ], 403);
        
        throw new HttpResponseException($response);
    }
}