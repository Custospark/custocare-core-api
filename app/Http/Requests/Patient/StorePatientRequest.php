<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StorePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return $this->user()->can('create', \App\Models\Patient::class);
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
            'user_id' => 'required|integer|exists:users,id|unique:patients,user_id',
            'medical_record_number_hash' => 'nullable|string|max:128|unique:patients',
            'medical_record_number_encrypted' => 'nullable|string|max:512',
            'previous_mrn_list_encrypted' => 'nullable|string|max:2048',
            'date_of_birth' => 'required|date|before:today',
            'biological_sex' => 'required|in:male,female,intersex,unknown',
            'gender_identity' => 'nullable|in:male,female,non_binary,prefer_not_to_say,other',
            'blood_type' => 'nullable|string|max:5',
            'ethnicity' => 'nullable|string|max:100',
            'genetic_markers' => 'nullable|array',
            'emergency_contact_chain_encrypted' => 'nullable|array',
            'known_allergies' => 'nullable|array',
            'chronic_conditions' => 'nullable|array',
            'active_medications' => 'nullable|array',
            'is_organ_donor' => 'boolean',
            'advance_directives' => 'nullable|array',
            'acuity_baseline' => 'integer|min:1|max:5',
            'risk_factors' => 'nullable|array',
            'requires_isolation' => 'boolean',
            'isolation_type' => 'nullable|string|max:50',
            'default_consent_level' => 'in:full,restricted,minimal,none',
            'privacy_flags' => 'array',
            'research_participation_allowed' => 'boolean',
            'data_sharing_allowed' => 'boolean',
            'primary_insurance_provider' => 'nullable|string|max:200',
            'primary_insurance_id_encrypted' => 'nullable|string|max:512',
            'secondary_insurance_provider' => 'nullable|string|max:200',
            'secondary_insurance_id_encrypted' => 'nullable|string|max:512',
            'payment_responsibility' => 'in:self_pay,insurance,government,charity',
            'primary_care_provider_staff_id' => 'nullable|integer|exists:staff,id',
            'primary_care_facility_id' => 'nullable|integer|exists:facilities,id',
            'portal_access_enabled' => 'boolean',
            'preferred_language' => 'string|max:10',
            'preferred_communication_method' => 'in:email,sms,phone,postal',
            'status' => 'in:active,inactive,deceased,merged,test_patient',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'This user already has a patient record.',
            'medical_record_number_hash.unique' => 'This medical record number already exists.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'acuity_baseline.min' => 'Acuity baseline must be at least 1.',
            'acuity_baseline.max' => 'Acuity baseline cannot exceed 5.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create patient records.',
            ], 403)
        );
    }
}