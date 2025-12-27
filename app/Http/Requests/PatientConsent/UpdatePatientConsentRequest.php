<?php

namespace App\Http\Requests\PatientConsent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdatePatientConsentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Authorization is handled by PatientConsentPolicy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'expires_at' => 'nullable|date|after:effective_from',
            'scope_limitations' => 'nullable|string|max:1000',
            'revocation_reason' => 'nullable|string|max:500|required_with:revoked_by_staff_id',
            'revoked_by_staff_id' => 'nullable|exists:staff,id|required_with:revocation_reason',
            'consent_document_storage_path' => 'nullable|string|max:512',
            'consent_document_metadata' => 'nullable|array',
            'metadata' => 'nullable|array',
            'audit_trail' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'expires_at.after' => 'The expiry date must be after the effective date.',
            'revocation_reason.required_with' => 'Revocation reason is required when specifying who revoked it.',
            'revoked_by_staff_id.required_with' => 'Revoked by staff ID is required when providing a revocation reason.',
            'revoked_by_staff_id.exists' => 'The specified staff member does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'expires_at' => 'expiry date',
            'scope_limitations' => 'scope limitations',
            'revocation_reason' => 'revocation reason',
            'revoked_by_staff_id' => 'revoked by staff',
            'consent_document_storage_path' => 'document storage path',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        Log::warning('Patient consent update validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Ensure timestamp is properly formatted
        if ($this->has('expires_at') && is_string($this->expires_at)) {
            $this->merge(['expires_at' => date('Y-m-d H:i:s', strtotime($this->expires_at))]);
        }
    }
}