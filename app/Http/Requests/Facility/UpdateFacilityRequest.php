<?php

namespace App\Http\Requests\Facility;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Facility;

/**
 * Class UpdateFacilityRequest
 * 
 * Form request for updating an existing facility.
 * Handles validation and authorization for facility updates.
 */
class UpdateFacilityRequest extends FormRequest
{
    /**
     * The facility instance.
     *
     * @var Facility|null
     */
    private ?Facility $facility = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $this->facility = $this->route('facility');
        
        return $this->user() && $this->user()->can('update', $this->facility);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $facilityId = $this->facility ? $this->facility->id : null;
        
        return [
            'facility_uuid' => 'sometimes|uuid|unique:facilities,facility_uuid,' . $facilityId,
            'facility_code' => 'sometimes|string|max:50|unique:facilities,facility_code,' . $facilityId,
            'facility_name' => 'sometimes|string|max:200',
            'legal_entity_name' => 'sometimes|string|max:200',
            'tax_id_encrypted' => 'nullable|string|max:512',
            
            'facility_type' => 'sometimes|in:hospital,clinic,urgent_care,emergency_department,ambulatory_surgery_center,diagnostic_center,rehabilitation_center,long_term_care,hospice,community_health_center,specialty_center,telehealth_hub',
            'facility_tier' => 'sometimes|in:tertiary,secondary,primary,specialized',
            'bed_capacity' => 'nullable|integer|min:0|max:65535',
            'accreditations' => 'nullable|array',
            
            'address_line1' => 'sometimes|string|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city' => 'sometimes|string|max:100',
            'state_province' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'country_code' => 'sometimes|string|size:3',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'sometimes|string|max:50|timezone',
            
            'main_phone' => 'sometimes|string|max:50',
            'emergency_phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:200',
            'website' => 'nullable|url|max:255',
            
            'operating_hours' => 'sometimes|array',
            'emergency_services_hours' => 'nullable|array',
            'is_24_7' => 'sometimes|boolean',
            
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'affiliated_facility_ids' => 'nullable|array',
            'referral_network_facility_ids' => 'nullable|array',
            'health_system_name' => 'nullable|string|max:200',
            
            'license_number' => 'nullable|string|max:100',
            'license_issuing_authority' => 'nullable|string|max:200',
            'license_expiry_date' => 'nullable|date|after:today',
            'regulatory_identifiers' => 'nullable|array',
            'participates_in_medicare' => 'sometimes|boolean',
            'participates_in_medicaid' => 'sometimes|boolean',
            
            'available_services' => 'sometimes|array|min:1',
            'specialty_services' => 'nullable|array',
            'equipment_inventory_summary' => 'nullable|array',
            'has_emergency_department' => 'sometimes|boolean',
            'has_trauma_center' => 'sometimes|boolean',
            'trauma_center_level' => 'nullable|required_if:has_trauma_center,true|integer|between:1,5',
            'has_intensive_care' => 'sometimes|boolean',
            'has_neonatal_icu' => 'sometimes|boolean',
            'has_cardiac_cath_lab' => 'sometimes|boolean',
            
            'data_residency_region' => 'sometimes|string|max:10',
            'primary_database_shard' => 'sometimes|string|max:50',
            'replica_shard_locations' => 'nullable|array',
            
            'average_wait_time_minutes' => 'nullable|numeric|min:0|max:999.99',
            'patient_satisfaction_score' => 'nullable|numeric|min:0|max:5',
            'monthly_patient_volume' => 'nullable|integer|min:0',
            
            'operational_status' => 'sometimes|in:fully_operational,limited_services,emergency_only,temporarily_closed,permanently_closed,under_construction',
            
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
            'facility_code.unique' => 'The facility code is already in use. Please use a unique facility code.',
            'trauma_center_level.required_if' => 'Trauma center level is required when the facility has a trauma center.',
            'license_expiry_date.after' => 'License expiry date must be a future date.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'email.email' => 'Please provide a valid email address.',
            'website.url' => 'Please provide a valid website URL.',
            'timezone.timezone' => 'Please provide a valid timezone.',
            'country_code.size' => 'Country code must be exactly 3 characters.',
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
            'facility_uuid' => 'Facility UUID',
            'facility_code' => 'Facility Code',
            'facility_name' => 'Facility Name',
            'legal_entity_name' => 'Legal Entity Name',
            'tax_id_encrypted' => 'Tax ID',
            'facility_type' => 'Facility Type',
            'facility_tier' => 'Facility Tier',
            'bed_capacity' => 'Bed Capacity',
            'accreditations' => 'Accreditations',
            'address_line1' => 'Address Line 1',
            'address_line2' => 'Address Line 2',
            'city' => 'City',
            'state_province' => 'State/Province',
            'postal_code' => 'Postal Code',
            'country_code' => 'Country Code',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
            'timezone' => 'Timezone',
            'main_phone' => 'Main Phone',
            'emergency_phone' => 'Emergency Phone',
            'fax' => 'Fax',
            'email' => 'Email',
            'website' => 'Website',
            'operating_hours' => 'Operating Hours',
            'emergency_services_hours' => 'Emergency Services Hours',
            'is_24_7' => '24/7 Operation',
            'parent_organization_id' => 'Parent Organization',
            'affiliated_facility_ids' => 'Affiliated Facility IDs',
            'referral_network_facility_ids' => 'Referral Network Facility IDs',
            'health_system_name' => 'Health System Name',
            'license_number' => 'License Number',
            'license_issuing_authority' => 'License Issuing Authority',
            'license_expiry_date' => 'License Expiry Date',
            'regulatory_identifiers' => 'Regulatory Identifiers',
            'participates_in_medicare' => 'Participates in Medicare',
            'participates_in_medicaid' => 'Participates in Medicaid',
            'available_services' => 'Available Services',
            'specialty_services' => 'Specialty Services',
            'equipment_inventory_summary' => 'Equipment Inventory Summary',
            'has_emergency_department' => 'Has Emergency Department',
            'has_trauma_center' => 'Has Trauma Center',
            'trauma_center_level' => 'Trauma Center Level',
            'has_intensive_care' => 'Has Intensive Care',
            'has_neonatal_icu' => 'Has Neonatal ICU',
            'has_cardiac_cath_lab' => 'Has Cardiac Cath Lab',
            'data_residency_region' => 'Data Residency Region',
            'primary_database_shard' => 'Primary Database Shard',
            'replica_shard_locations' => 'Replica Shard Locations',
            'average_wait_time_minutes' => 'Average Wait Time',
            'patient_satisfaction_score' => 'Patient Satisfaction Score',
            'monthly_patient_volume' => 'Monthly Patient Volume',
            'operational_status' => 'Operational Status',
            'metadata' => 'Metadata',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()->toArray(),
            'data' => null
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this facility.',
            'errors' => ['authorization' => ['Unauthorized action.']],
            'data' => null
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
        // Ensure boolean fields are properly cast
        $booleanFields = [
            'is_24_7',
            'participates_in_medicare',
            'participates_in_medicaid',
            'has_emergency_department',
            'has_trauma_center',
            'has_intensive_care',
            'has_neonatal_icu',
            'has_cardiac_cath_lab',
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->{$field}, FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        // Ensure array fields are properly decoded if they come as JSON strings
        $arrayFields = [
            'accreditations',
            'operating_hours',
            'emergency_services_hours',
            'affiliated_facility_ids',
            'referral_network_facility_ids',
            'regulatory_identifiers',
            'available_services',
            'specialty_services',
            'equipment_inventory_summary',
            'replica_shard_locations',
            'metadata',
        ];

        foreach ($arrayFields as $field) {
            if ($this->has($field) && is_string($this->{$field})) {
                $decoded = json_decode($this->{$field}, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }

        // Don't update facility UUID if not provided or empty
        if ($this->has('facility_uuid') && empty($this->facility_uuid)) {
            $this->offsetUnset('facility_uuid');
        }
    }
}