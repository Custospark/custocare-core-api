<?php

namespace App\Http\Requests\ServiceCatalog;

use App\Models\ServiceCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateServiceCatalogRequest extends FormRequest
{
    /**
     * The service catalog being updated.
     *
     * @var ServiceCatalog|null
     */
    protected ?ServiceCatalog $serviceCatalog = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Find the service catalog by UUID
        $this->serviceCatalog = ServiceCatalog::where('service_uuid', $this->route('serviceCatalog'))->first();
        
        if (!$this->serviceCatalog) {
            return false;
        }

        // Check if user has permission to update service catalogs
        // Example: return $this->user()->can('update', $this->serviceCatalog);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $serviceUuid = $this->route('serviceCatalog');
        
        return [
            'service_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'unique:service_catalogs,service_code,' . $serviceUuid . ',service_uuid'
            ],
            'code_system' => [
                'sometimes',
                'required',
                'string',
                'in:cpt,hcpcs,icd_10_pcs,cdt,local_custom'
            ],
            'service_name' => [
                'sometimes',
                'required',
                'string',
                'max:300'
            ],
            'service_description' => [
                'nullable',
                'string'
            ],
            'alternate_names' => [
                'nullable',
                'array'
            ],
            'alternate_names.*' => [
                'string',
                'max:300'
            ],
            'service_category' => [
                'sometimes',
                'required',
                'string',
                'in:evaluation_management,diagnostic_imaging,laboratory_test,surgical_procedure,medical_procedure,therapy_session,preventive_care,vaccination,medication_administration,emergency_service,consultation,anesthesia,pathology,radiology,facility_fee'
            ],
            'service_subcategories' => [
                'nullable',
                'array'
            ],
            'service_subcategories.*' => [
                'string',
                'max:100'
            ],
            'department_specialty' => [
                'nullable',
                'string',
                'max:100'
            ],
            'regulatory_approval_status' => [
                'nullable',
                'array'
            ],
            'required_certifications' => [
                'nullable',
                'array'
            ],
            'required_certifications.*' => [
                'string',
                'max:200'
            ],
            'minimum_required_credentials' => [
                'nullable',
                'array'
            ],
            'minimum_required_credentials.*' => [
                'string',
                'max:200'
            ],
            'required_equipment' => [
                'nullable',
                'array'
            ],
            'required_equipment.*' => [
                'string',
                'max:200'
            ],
            'required_facility_capabilities' => [
                'nullable',
                'array'
            ],
            'required_facility_capabilities.*' => [
                'string',
                'max:200'
            ],
            'default_duration_minutes' => [
                'nullable',
                'integer',
                'min:1',
                'max:1440'
            ],
            'typical_indications' => [
                'nullable',
                'array'
            ],
            'typical_indications.*' => [
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
            'prerequisites' => [
                'nullable',
                'array'
            ],
            'prerequisites.*' => [
                'string',
                'max:500'
            ],
            'commonly_paired_services' => [
                'nullable',
                'array'
            ],
            'commonly_paired_services.*' => [
                'string',
                'max:50'
            ],
            'risk_level' => [
                'nullable',
                'string',
                'in:low,moderate,high,critical'
            ],
            'requires_informed_consent' => [
                'nullable',
                'boolean'
            ],
            'consent_form_template' => [
                'nullable',
                'string',
                'max:200'
            ],
            'applicable_region' => [
                'sometimes',
                'required',
                'string',
                'max:10'
            ],
            'approved_countries' => [
                'nullable',
                'array'
            ],
            'approved_countries.*' => [
                'string',
                'size:2',
                'alpha'
            ],
            'state_specific_regulations' => [
                'nullable',
                'array'
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,inactive,deprecated,under_review'
            ],
            'effective_from' => [
                'sometimes',
                'required',
                'date_format:Y-m-d'
            ],
            'effective_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after:effective_from'
            ],
            'metadata' => [
                'nullable',
                'array'
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
            'service_code.unique' => 'This service code already exists. Please use a different code.',
            'service_code.max' => 'The service code must not exceed 50 characters.',
            'code_system.in' => 'The selected code system is invalid. Valid options are: CPT, HCPCS, ICD-10-PCS, CDT, or local custom.',
            'service_name.max' => 'The service name must not exceed 300 characters.',
            'service_category.in' => 'The selected service category is invalid.',
            'applicable_region.max' => 'The applicable region must not exceed 10 characters.',
            'effective_from.date_format' => 'The effective from date must be in the format YYYY-MM-DD.',
            'effective_to.date_format' => 'The effective to date must be in the format YYYY-MM-DD.',
            'effective_to.after' => 'The effective to date must be after the effective from date.',
            'approved_countries.*.size' => 'Each country code must be exactly 2 characters.',
            'approved_countries.*.alpha' => 'Country codes must contain only letters.',
            'default_duration_minutes.max' => 'The default duration cannot exceed 1440 minutes (24 hours).'
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
            'service_code' => 'service code',
            'code_system' => 'code system',
            'service_name' => 'service name',
            'service_category' => 'service category',
            'applicable_region' => 'applicable region',
            'effective_from' => 'effective from date',
            'effective_to' => 'effective to date'
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
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
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
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this service catalog.',
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
        if ($this->has('requires_informed_consent')) {
            $this->merge([
                'requires_informed_consent' => filter_var($this->requires_informed_consent, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Parse JSON strings to arrays if provided as strings
        $jsonFields = [
            'alternate_names',
            'service_subcategories',
            'regulatory_approval_status',
            'required_certifications',
            'minimum_required_credentials',
            'required_equipment',
            'required_facility_capabilities',
            'typical_indications',
            'contraindications',
            'prerequisites',
            'commonly_paired_services',
            'approved_countries',
            'state_specific_regulations',
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
    }
}