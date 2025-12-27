<?php

namespace App\Http\Requests\PatientConsent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StorePatientConsentRequest extends FormRequest
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
            'patient_id' => 'required|exists:patients,id',
            'consent_type' => 'required|in:treatment,procedures,anesthesia,blood_transfusion,research,data_sharing,marketing,photography,teaching,organ_donation,release_of_info',
            'scope_facility_ids' => 'nullable|array',
            'scope_facility_ids.*' => 'integer|exists:facilities,id',
            'scope_department_ids' => 'nullable|array',
            'scope_department_ids.*' => 'integer|exists:departments,id',
            'scope_staff_ids' => 'nullable|array',
            'scope_staff_ids.*' => 'integer|exists:staff,id',
            'scope_service_categories' => 'nullable|array',
            'scope_service_categories.*' => 'string|max:50',
            'scope_limitations' => 'nullable|string|max:1000',
            'legal_basis' => 'required|in:explicit_consent,contractual,legal_obligation,vital_interests,legitimate_interest',
            'granted_at' => 'required|date|before_or_equal:now',
            'effective_from' => 'required|date|after_or_equal:granted_at',
            'expires_at' => 'nullable|date|after:effective_from',
            'witnessed_by_staff_id' => 'nullable|exists:staff,id',
            'witness_signature_hash' => 'nullable|string|size:128',
            'patient_signature_hash' => 'required|string|size:128',
            'signature_method' => 'nullable|in:digital,wet_signature,verbal,implied',
            'consent_ip_address' => 'nullable|ip',
            'consent_user_agent' => 'nullable|string|max:500',
            'consent_device_fingerprint' => 'nullable|string|size:128',
            'consent_geolocation' => 'nullable|string|max:100',
            'consent_form_version' => 'required|string|max:20',
            'consent_document_hash' => 'required|string|size:64',
            'consent_document_storage_path' => 'nullable|string|max:512',
            'consent_document_metadata' => 'nullable|array',
            'consent_language' => 'nullable|string|size:2',
            'interpreter_used' => 'boolean',
            'interpreter_language' => 'nullable|string|max:50',
            'capacity_confirmed' => 'boolean',
            'legal_guardian_id' => 'nullable|exists:patients,id',
            'metadata' => 'nullable|array',
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
            'patient_id.required' => 'The patient ID is required.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'consent_type.required' => 'The consent type is required.',
            'consent_type.in' => 'The selected consent type is invalid.',
            'granted_at.before_or_equal' => 'The granted date cannot be in the future.',
            'effective_from.after_or_equal' => 'The effective date must be after or equal to the granted date.',
            'expires_at.after' => 'The expiry date must be after the effective date.',
            'patient_signature_hash.required' => 'Patient signature hash is required.',
            'patient_signature_hash.size' => 'Patient signature hash must be exactly 128 characters.',
            'consent_document_hash.required' => 'Document hash is required for audit trail.',
            'consent_document_hash.size' => 'Document hash must be exactly 64 characters (SHA-256).',
            'consent_form_version.required' => 'Consent form version is required.',
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
            'patient_id' => 'patient',
            'consent_type' => 'consent type',
            'scope_facility_ids' => 'facility scope',
            'scope_department_ids' => 'department scope',
            'scope_staff_ids' => 'staff scope',
            'granted_at' => 'granted date',
            'effective_from' => 'effective date',
            'expires_at' => 'expiry date',
            'patient_signature_hash' => 'patient signature',
            'consent_document_hash' => 'document hash',
            'consent_form_version' => 'form version',
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
        Log::warning('Patient consent store validation failed', [
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
        // Set default values
        if (!$this->has('legal_basis')) {
            $this->merge(['legal_basis' => 'explicit_consent']);
        }

        if (!$this->has('consent_language')) {
            $this->merge(['consent_language' => 'en']);
        }

        if (!$this->has('capacity_confirmed')) {
            $this->merge(['capacity_confirmed' => true]);
        }

        if (!$this->has('interpreter_used')) {
            $this->merge(['interpreter_used' => false]);
        }

        // Ensure timestamps are properly formatted
        if ($this->has('granted_at') && is_string($this->granted_at)) {
            $this->merge(['granted_at' => date('Y-m-d H:i:s', strtotime($this->granted_at))]);
        }

        if ($this->has('effective_from') && is_string($this->effective_from)) {
            $this->merge(['effective_from' => date('Y-m-d H:i:s', strtotime($this->effective_from))]);
        }

        if ($this->has('expires_at') && is_string($this->expires_at)) {
            $this->merge(['expires_at' => date('Y-m-d H:i:s', strtotime($this->expires_at))]);
        }
    }
}