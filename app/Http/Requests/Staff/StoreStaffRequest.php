<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class StoreStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by Policy
        // return $this->user()->can('create', \App\Models\Staff::class);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'professional_title' => 'required|string|max:100',
            'professional_license_number_encrypted' => 'nullable|string|max:512',
            'license_issuing_state' => 'nullable|string|max:50',
            'license_issuing_country' => 'nullable|string|size:3',
            'license_expiry_date' => 'nullable|date|after:today',
            
            'specialization_codes' => 'nullable|array',
            'specialization_codes.*' => 'string|max:10',
            'board_certifications' => 'nullable|array',
            'additional_certifications' => 'nullable|array',
            'npi_number' => 'nullable|string|max:20|unique:staff,npi_number',
            'dea_number_encrypted' => 'nullable|string|max:512',
            'dea_expiry_date' => 'nullable|date|after:today',
            
            'employment_status' => 'required|in:employed,unemployed,suspended,terminated,retired,credentialing_pending',
            'employment_type' => 'nullable|in:full_time,part_time,contract,locum_tenens,volunteer',
            'hire_date' => 'nullable|date|before_or_equal:today',
            'termination_date' => 'nullable|date|after_or_equal:hire_date',
            'termination_reason' => 'nullable|string|max:1000',
            
            'clinical_privileges' => 'nullable|array',
            'prescribing_authority' => 'nullable|array',
            'can_supervise_trainees' => 'boolean',
            'can_order_controlled_substances' => 'boolean',
            'can_sign_death_certificates' => 'boolean',
            
            'global_role_level' => 'nullable|in:super_admin,facility_admin,department_head,attending_physician,fellow,resident,nurse_practitioner,physician_assistant,registered_nurse,licensed_practical_nurse,pharmacist,therapist,technician,support_staff',
            'reports_to_staff_id' => 'nullable|integer|exists:staff,id',
            
            'default_schedule' => 'nullable|array',
            'max_concurrent_patients' => 'nullable|integer|min:1|max:100',
            'average_appointment_duration_minutes' => 'nullable|integer|min:5|max:240',
            'accepts_new_patients' => 'boolean',
            
            'patient_satisfaction_score' => 'nullable|numeric|between:0.00,5.00',
            'total_patients_treated' => 'nullable|integer|min:0',
            'quality_metrics' => 'nullable|array',
            
            'background_check_completed' => 'boolean',
            'background_check_date' => 'nullable|date|before_or_equal:today',
            'drug_screening_completed' => 'boolean',
            'drug_screening_date' => 'nullable|date|before_or_equal:today',
            'immunization_records' => 'nullable|array',
            'tb_test_records' => 'nullable|array',
            'hipaa_training_completed' => 'boolean',
            'hipaa_training_date' => 'nullable|date|before_or_equal:today',
            'hipaa_training_expiry' => 'nullable|date|after:hipaa_training_date',
            
            'work_phone_encrypted' => 'nullable|string|max:512',
            'work_email_encrypted' => 'nullable|string|max:512',
            'emergency_contact_encrypted' => 'nullable|array',
            
            'system_permissions' => 'nullable|array',
            'accessible_facility_ids' => 'nullable|array',
            'accessible_department_ids' => 'nullable|array',
            
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
            'user_id.unique' => 'This user is required and must be unique.',
            'employee_id.unique' => 'This employee ID is already in use.',
            'npi_number.unique' => 'This NPI number is already registered.',
            'license_expiry_date.after' => 'License expiry date must be in the future.',
            'dea_expiry_date.after' => 'DEA expiry date must be in the future.',
            'hire_date.before_or_equal' => 'Hire date cannot be in the future.',
            'termination_date.after_or_equal' => 'Termination date must be after hire date.',
            'hipaa_training_expiry.after' => 'HIPAA training expiry must be after training date.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
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
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create staff records.'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure arrays are properly formatted
        $this->merge([
            'specialization_codes' => $this->parseJsonField('specialization_codes'),
            'board_certifications' => $this->parseJsonField('board_certifications'),
            'additional_certifications' => $this->parseJsonField('additional_certifications'),
            'clinical_privileges' => $this->parseJsonField('clinical_privileges'),
            'prescribing_authority' => $this->parseJsonField('prescribing_authority'),
            'default_schedule' => $this->parseJsonField('default_schedule'),
            'quality_metrics' => $this->parseJsonField('quality_metrics'),
            'immunization_records' => $this->parseJsonField('immunization_records'),
            'tb_test_records' => $this->parseJsonField('tb_test_records'),
            'emergency_contact_encrypted' => $this->parseJsonField('emergency_contact_encrypted'),
            'system_permissions' => $this->parseJsonField('system_permissions'),
            'accessible_facility_ids' => $this->parseJsonField('accessible_facility_ids'),
            'accessible_department_ids' => $this->parseJsonField('accessible_department_ids'),
            'metadata' => $this->parseJsonField('metadata'),
        ]);
    }

    /**
     * Parse JSON field from request.
     */
    private function parseJsonField(string $field): ?array
    {
        $value = $this->input($field);
        
        if (is_null($value)) {
            return null;
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        
        return null;
    }
}