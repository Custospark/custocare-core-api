<?php

namespace App\Http\Requests\StaffCredential;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateStaffCredentialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by policy
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_id' => 'sometimes|integer|exists:staff,id',
            'credential_type' => [
                'sometimes',
                'string',
                Rule::in([
                    'medical_license',
                    'board_certification',
                    'dea_registration',
                    'cds_registration',
                    'malpractice_insurance',
                    'professional_liability',
                    'cpr_certification',
                    'acls_certification',
                    'pals_certification',
                    'bls_certification',
                    'specialty_training',
                    'continuing_education',
                    'privileging',
                    'hospital_affiliation'
                ])
            ],
            'credential_name' => 'sometimes|string|max:200',
            'credential_number_encrypted' => 'nullable|string|max:512',
            'credential_number_hash' => 'nullable|string|max:128',
            'issuing_authority' => 'sometimes|string|max:200',
            'issuing_authority_contact' => 'nullable|string|max:200',
            'issuing_state_country' => 'nullable|string|max:100',
            'issued_date' => 'sometimes|date',
            'valid_from' => 'sometimes|date',
            'valid_to' => 'nullable|date',
            'requires_renewal' => 'boolean',
            'renewal_reminder_date' => 'nullable|date',
            'verification_status' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'verified', 'expired', 'suspended', 'revoked', 'under_review'])
            ],
            'verified_at' => 'nullable|date',
            'verified_by_staff_id' => 'nullable|integer|exists:staff,id',
            'verification_method' => 'nullable|string|max:100|in:primary_source,database_check,document_review',
            'verification_notes' => 'nullable|string|max:1000',
            'credential_document_hash' => 'sometimes|string|max:128',
            'document_storage_path' => 'nullable|string|max:512',
            'document_mime_type' => 'nullable|string|max:100',
            'document_size_bytes' => 'nullable|integer|min:0',
            'is_current' => 'boolean',
            'superseded_by_credential_id' => 'nullable|integer|exists:staff_credentials,id',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
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
            'staff_id.exists' => 'The selected staff member does not exist.',
            'credential_type.in' => 'The selected credential type is invalid.',
            'valid_to.after' => 'The valid to date must be after the valid from date.',
            'verification_status.in' => 'The selected verification status is invalid.',
            'verified_by_staff_id.exists' => 'The selected verifying staff member does not exist.',
            'superseded_by_credential_id.exists' => 'The selected superseding credential does not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'status' => 422
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean fields are properly cast
        $booleanFields = ['requires_renewal', 'is_current'];
        
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    /**
     * Get validated data with only the fields that were provided
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);
        
        // Remove null values for fields that weren't provided
        foreach ($validated as $field => $value) {
            if ($value === null && !$this->has($field)) {
                unset($validated[$field]);
            }
        }
        
        return $validated;
    }
}