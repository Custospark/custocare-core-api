<?php

namespace App\Http\Requests\Facility;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Class UpdateFacilitySettingsRequest
 *
 * Form request for PUT /facilities/{facility}/settings.
 *
 * Every field uses `sometimes` so the client may send only the fields
 * it wishes to change (partial updates are fully supported).
 *
 * Fields are organised to mirror the settings grouping:
 *   CoreIdentity · Classification · CapacityAndServices · Location ·
 *   ContactInformation · Operations · LicensingAndCompliance ·
 *   ClinicalCapabilities · FinancialConfiguration · Branding (colours only) ·
 *   SystemConfiguration
 *
 * NOTE: `facility_logo_path` is intentionally excluded here.
 *       Logo uploads are handled by POST /facilities/{facility}/settings/logo.
 *
 * NOTE: `description` is excluded – the column does not exist in the
 *       facilities migration. Add the column and rule here once it is added.
 */
class UpdateFacilitySettingsRequest extends FormRequest
{
    // ──────────────────────────────────────────────────────────────────────────
    // Authorization
    // ──────────────────────────────────────────────────────────────────────────

    public function authorize(): bool
    {
        return Auth::check();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Validation Rules
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [

            // ── CoreIdentity ─────────────────────────────────────────────────
            'facility_name'               => 'sometimes|string|max:200',
            'legal_entity_name'           => 'sometimes|string|max:200',
            'health_system_name'          => 'sometimes|nullable|string|max:200',

            // ── Classification ───────────────────────────────────────────────
            'nature_of_facility'          => 'sometimes|in:government,private,faith_based,ngo,military,academic,public_private_partnership',
            'facility_type'               => 'sometimes|in:hospital,clinic,urgent_care,emergency_department,ambulatory_surgery_center,diagnostic_center,rehabilitation_center,long_term_care,hospice,community_health_center,specialty_center,telehealth_hub,laboratory,pharmacy',
            'facility_tier'               => 'sometimes|in:tertiary,secondary,primary,specialized',

            // ── CapacityAndServices ──────────────────────────────────────────
            'bed_capacity'                => 'sometimes|nullable|integer|min:0|max:65535',
            'available_services'          => 'sometimes|array',
            'specialty_services'          => 'sometimes|nullable|array',
            'equipment_inventory_summary' => 'sometimes|nullable|array',

            // ── Location ─────────────────────────────────────────────────────
            'address_line1'               => 'sometimes|string|max:200',
            'address_line2'               => 'sometimes|nullable|string|max:200',
            'city'                        => 'sometimes|string|max:100',
            'state_province'              => 'sometimes|string|max:100',
            'postal_code'                 => 'sometimes|string|max:20',
            'country_code'                => 'sometimes|string|max:2',
            'latitude'                    => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'                   => 'sometimes|nullable|numeric|between:-180,180',

            // ── ContactInformation ───────────────────────────────────────────
            'main_phone'                  => 'sometimes|string|max:50',
            'emergency_phone'             => 'sometimes|nullable|string|max:50',
            'fax'                         => 'sometimes|nullable|string|max:50',
            'email'                       => 'sometimes|nullable|email|max:200',
            'website'                     => 'sometimes|nullable|url|max:255',

            // ── Operations ───────────────────────────────────────────────────
            'operating_hours'             => 'sometimes|array',
            'emergency_services_hours'    => 'sometimes|nullable|array',
            'is_24_7'                     => 'sometimes|boolean',
            'operational_status'          => 'sometimes|in:fully_operational,limited_services,emergency_only,temporarily_closed,permanently_closed,under_construction',
            'average_wait_time_minutes'   => 'sometimes|nullable|numeric|min:0|max:999.99',
            'monthly_patient_volume'      => 'sometimes|nullable|integer|min:0',

            // ── LicensingAndCompliance ───────────────────────────────────────
            'license_number'              => 'sometimes|nullable|string|max:100',
            'license_issuing_authority'   => 'sometimes|nullable|string|max:200',
            'license_expiry_date'         => 'sometimes|nullable|date',
            'regulatory_identifiers'      => 'sometimes|nullable|array',
            'participates_in_medicare'    => 'sometimes|boolean',
            'participates_in_medicaid'    => 'sometimes|boolean',

            // ── ClinicalCapabilities ─────────────────────────────────────────
            'has_emergency_department'    => 'sometimes|boolean',
            'has_trauma_center'           => 'sometimes|boolean',
            'trauma_center_level'         => 'sometimes|nullable|integer|between:1,5',
            'has_intensive_care'          => 'sometimes|boolean',
            'has_neonatal_icu'            => 'sometimes|boolean',
            'has_cardiac_cath_lab'        => 'sometimes|boolean',

            // ── FinancialConfiguration ───────────────────────────────────────
            'currency'                    => 'sometimes|string|size:3|alpha',
            'tax_enabled'                 => 'sometimes|boolean',
            'tax_name'                    => 'sometimes|nullable|string|max:255',
            'tax_rate'                    => 'sometimes|nullable|numeric|min:0|max:100',

            // ── Branding (colours only — logo handled via upload endpoint) ───
            'primary_brand_color'         => ['sometimes', 'nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'secondary_brand_color'       => ['sometimes', 'nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],

            // ── SystemConfiguration ──────────────────────────────────────────
            'timezone'                    => 'sometimes|string|max:50',
            'data_residency_region'       => 'sometimes|nullable|string|max:10',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Custom Messages
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nature_of_facility.in'         => 'Nature of facility must be one of: government, private, faith_based, ngo, military, academic, public_private_partnership.',
            'facility_type.in'              => 'Facility type must be one of the accepted values (e.g. hospital, clinic, urgent_care…).',
            'facility_tier.in'              => 'Facility tier must be one of: tertiary, secondary, primary, specialized.',
            'operational_status.in'         => 'Operational status must be one of: fully_operational, limited_services, emergency_only, temporarily_closed, permanently_closed, under_construction.',
            'country_code.max'              => 'Country code must not exceed 2 characters.',
            'currency.size'                 => 'Currency must be exactly 3 characters (ISO 4217, e.g. USD).',
            'trauma_center_level.between'   => 'Trauma center level must be between 1 and 5.',
            'primary_brand_color.regex'     => 'Primary brand color must be a valid hex colour code (e.g. #FFFFFF or #FFF).',
            'secondary_brand_color.regex'   => 'Secondary brand color must be a valid hex colour code (e.g. #FFFFFF or #FFF).',
            'latitude.between'              => 'Latitude must be between -90 and 90.',
            'longitude.between'             => 'Longitude must be between -180 and 180.',
            'tax_rate.max'                  => 'Tax rate must not exceed 100.',
            'average_wait_time_minutes.max' => 'Average wait time must not exceed 999.99 minutes.',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Attribute Names
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'facility_name'               => 'Facility Name',
            'legal_entity_name'           => 'Legal Entity Name',
            'health_system_name'          => 'Health System Name',
            'nature_of_facility'          => 'Nature of Facility',
            'facility_type'               => 'Facility Type',
            'facility_tier'               => 'Facility Tier',
            'bed_capacity'                => 'Bed Capacity',
            'available_services'          => 'Available Services',
            'specialty_services'          => 'Specialty Services',
            'equipment_inventory_summary' => 'Equipment Inventory Summary',
            'address_line1'               => 'Address Line 1',
            'address_line2'               => 'Address Line 2',
            'city'                        => 'City',
            'state_province'              => 'State / Province',
            'postal_code'                 => 'Postal Code',
            'country_code'                => 'Country Code',
            'latitude'                    => 'Latitude',
            'longitude'                   => 'Longitude',
            'main_phone'                  => 'Main Phone',
            'emergency_phone'             => 'Emergency Phone',
            'fax'                         => 'Fax',
            'email'                       => 'Email',
            'website'                     => 'Website',
            'operating_hours'             => 'Operating Hours',
            'emergency_services_hours'    => 'Emergency Services Hours',
            'is_24_7'                     => '24/7 Availability',
            'operational_status'          => 'Operational Status',
            'average_wait_time_minutes'   => 'Average Wait Time (Minutes)',
            'monthly_patient_volume'      => 'Monthly Patient Volume',
            'license_number'              => 'License Number',
            'license_issuing_authority'   => 'License Issuing Authority',
            'license_expiry_date'         => 'License Expiry Date',
            'regulatory_identifiers'      => 'Regulatory Identifiers',
            'participates_in_medicare'    => 'Participates in Medicare',
            'participates_in_medicaid'    => 'Participates in Medicaid',
            'has_emergency_department'    => 'Has Emergency Department',
            'has_trauma_center'           => 'Has Trauma Center',
            'trauma_center_level'         => 'Trauma Center Level',
            'has_intensive_care'          => 'Has Intensive Care',
            'has_neonatal_icu'            => 'Has Neonatal ICU',
            'has_cardiac_cath_lab'        => 'Has Cardiac Cath Lab',
            'currency'                    => 'Currency',
            'tax_enabled'                 => 'Tax Enabled',
            'tax_name'                    => 'Tax Name',
            'tax_rate'                    => 'Tax Rate',
            'primary_brand_color'         => 'Primary Brand Color',
            'secondary_brand_color'       => 'Secondary Brand Color',
            'timezone'                    => 'Timezone',
            'data_residency_region'       => 'Data Residency Region',
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Hooks
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Trim all incoming string fields before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $stringFields = [
            'facility_name', 'legal_entity_name', 'health_system_name',
            'address_line1', 'address_line2', 'city', 'state_province',
            'postal_code', 'country_code',
            'main_phone', 'emergency_phone', 'fax', 'email', 'website',
            'license_number', 'license_issuing_authority',
            'currency', 'tax_name',
            'primary_brand_color', 'secondary_brand_color',
            'timezone', 'data_residency_region',
        ];

        foreach ($stringFields as $field) {
            if ($this->has($field) && is_string($this->{$field})) {
                $this->merge([$field => trim($this->{$field})]);
            }
        }

        // Uppercase currency code (ISO 4217)
        if ($this->has('currency') && is_string($this->currency)) {
            $this->merge(['currency' => strtoupper(trim($this->currency))]);
        }
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $validator->errors()->toArray(),
            'data'    => null,
        ], 422));
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this facility\'s settings.',
            'errors'  => ['authorization' => ['Unauthorized action.']],
            'data'    => null,
        ], 403));
    }
}
