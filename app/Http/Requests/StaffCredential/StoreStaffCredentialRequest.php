<?php

namespace App\Http\Requests\StaffCredential;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreStaffCredentialRequest extends FormRequest
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
            'staff_id' => 'required|integer|exists:staff,id',
            'credential_type' => [
                'required',
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
            'credential_name' => 'required|string|max:200',
            'credential_number_encrypted' => 'nullable|string|max:512',
            'credential_number_hash' => 'nullable|string|max:128',
            'issuing_authority' => 'required|string|max:200',
            'issuing_authority_contact' => 'nullable|string|max:200',
            'issuing_state_country' => 'nullable|string|max:100',
            'issued_date' => 'required|date|before_or_equal:today',
            'valid_from' => 'required|date|after_or_equal:issued_date',
            'valid_to' => 'nullable|date|after:valid_from',
            'requires_renewal' => 'boolean',
            'renewal_reminder_date' => 'nullable|date|after:today',
            'verification_status' => [
                'required',
                'string',
                Rule::in(['pending', 'verified', 'expired', 'suspended', 'revoked', 'under_review'])
            ],
            'verified_at' => 'nullable|date|before_or_equal:now',
            'verified_by_staff_id' => 'nullable|integer|exists:staff,id',
            'verification_method' => 'nullable|string|max:100|in:primary_source,database_check,document_review',
            'verification_notes' => 'nullable|string|max:1000',
            'credential_document_hash' => 'required|string|max:128',
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
            'staff_id.required' => 'The staff ID is required.',
            'staff_id.exists' => 'The selected staff member does not exist.',
            'credential_type.required' => 'The credential type is required.',
            'credential_type.in' => 'The selected credential type is invalid.',
            'credential_name.required' => 'The credential name is required.',
            'issued_date.required' => 'The issued date is required.',
            'issued_date.before_or_equal' => 'The issued date cannot be in the future.',
            'valid_from.required' => 'The valid from date is required.',
            'valid_from.after_or_equal' => 'The valid from date must be on or after the issued date.',
            'valid_to.after' => 'The valid to date must be after the valid from date.',
            'verification_status.required' => 'The verification status is required.',
            'verification_status.in' => 'The selected verification status is invalid.',
            'credential_document_hash.required' => 'The credential document hash is required.',
            'credential_document_hash.max' => 'The credential document hash may not be greater than 128 characters.',
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
        // Set created_by_staff_id from authenticated user if not provided
        if (!$this->has('created_by_staff_id') && Auth::check()) {
            $this->merge([
                'created_by_staff_id' => Auth::id(),
            ]);
        }

        // Ensure boolean fields are properly cast
        $this->merge([
            'requires_renewal' => filter_var($this->requires_renewal, FILTER_VALIDATE_BOOLEAN),
            'is_current' => filter_var($this->is_current ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        // Set default verification status if not provided
        if (!$this->has('verification_status')) {
            $this->merge([
                'verification_status' => 'pending',
            ]);
        }
    }
}