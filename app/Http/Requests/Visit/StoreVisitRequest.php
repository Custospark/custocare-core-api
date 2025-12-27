<?php

namespace App\Http\Requests\Visit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

/**
 * Store Visit Request
 *
 * Validates and authorizes store visit requests
 */
class StoreVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Check if user has permission to create visits
        return $this->user()->can('create', \App\Models\Visit::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'visit_type' => 'required|string|in:outpatient,inpatient,emergency,urgent_care,virtual_telehealth,home_health,observation,day_surgery,consultation,followup,preventive_wellness',
            'visit_subtype' => 'nullable|string|in:new_patient,established_patient,annual_physical,sick_visit,injury,procedure,diagnostic,therapy_session',
            'acuity_score' => 'nullable|integer|min:1|max:5',
            'chief_complaints' => 'required|array',
            'chief_complaints.*' => 'string|max:500',
            'symptoms_on_arrival' => 'nullable|array',
            'symptoms_on_arrival.*' => 'string|max:500',
            'patient_reported_history' => 'nullable|string|max:2000',
            'arrived_at' => 'required|date',
            'registered_at' => 'nullable|date',
            'mode_of_arrival' => 'nullable|string|in:walk_in,ambulance,private_vehicle,police_transport,air_ambulance,wheelchair_transport,transfer_from_facility',
            'accompanying_person' => 'nullable|string|max:200',
            'referring_facility_id' => 'nullable|integer|exists:facilities,id',
            'referring_provider_staff_id' => 'nullable|integer|exists:staff,id',
            'external_referral_id' => 'nullable|string|max:100',
            'referral_reason' => 'nullable|string|max:1000',
            'current_department_id' => 'nullable|integer|exists:departments,id',
            'current_phase' => 'nullable|string|in:registration,waiting_triage,triage,waiting_provider,consultation,diagnostic_tests,awaiting_results,treatment,procedures,observation,admission_pending,billing,discharge_pending,discharged,left_without_being_seen,left_against_medical_advice,transferred,admitted,expired',
            'waiting_since' => 'nullable|date',
            'clinical_care_started_at' => 'nullable|date',
            'clinical_care_ended_at' => 'nullable|date',
            'expected_duration_minutes' => 'nullable|integer|min:1|max:1440',
            'actual_duration_minutes' => 'nullable|integer|min:1|max:1440',
            'scheduled_appointment_id' => 'nullable|integer',
            'is_walk_in' => 'nullable|boolean',
            'scheduled_time' => 'nullable|date',
            'insurance_preauth_id' => 'nullable|string|max:100',
            'insurance_verification_status' => 'nullable|string|in:not_verified,verified,pending,denied,not_applicable',
            'insurance_verified_at' => 'nullable|date',
            'vital_signs_summary' => 'nullable|array',
            'diagnosis_codes' => 'nullable|array',
            'diagnosis_codes.*' => 'string|max:20',
            'procedure_codes' => 'nullable|array',
            'procedure_codes.*' => 'string|max:20',
            'medications_administered' => 'nullable|array',
            'requires_interpreter' => 'nullable|boolean',
            'interpreter_language' => 'nullable|string|max:50',
            'isolation_required' => 'nullable|boolean',
            'isolation_type' => 'nullable|string|max:50',
            'estimated_total_charges' => 'nullable|numeric|min:0|max:9999999999.99',
            'patient_estimated_responsibility' => 'nullable|numeric|min:0|max:999999999.99',
            'payment_status' => 'nullable|string|in:not_billed,pending,partially_paid,paid_in_full,insurance_pending,denied,bad_debt,charity_care',
            'status' => 'nullable|string|in:active,completed,cancelled,no_show,in_progress',
            'cancellation_reason' => 'nullable|string|max:1000',
            'cancelled_at' => 'nullable|date',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'updated_by_staff_id' => 'nullable|integer|exists:staff,id',
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
            'facility_id.required' => 'Please select a facility.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'patient_id.required' => 'Please select a patient.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'visit_type.required' => 'Please select a visit type.',
            'visit_type.in' => 'The selected visit type is invalid.',
            'acuity_score.min' => 'Acuity score must be at least 1.',
            'acuity_score.max' => 'Acuity score must not exceed 5.',
            'chief_complaints.required' => 'Please provide chief complaints.',
            'arrived_at.required' => 'Please provide arrival time.',
            'arrived_at.date' => 'Please provide a valid arrival time.',
            'estimated_total_charges.numeric' => 'Estimated total charges must be a number.',
            'estimated_total_charges.min' => 'Estimated total charges cannot be negative.',
            'patient_estimated_responsibility.numeric' => 'Patient estimated responsibility must be a number.',
            'patient_estimated_responsibility.min' => 'Patient estimated responsibility cannot be negative.',
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
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to create visits.',
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
        // Set default values
        $this->merge([
            'acuity_score' => $this->acuity_score ?? 3,
            'is_walk_in' => $this->is_walk_in ?? false,
            'requires_interpreter' => $this->requires_interpreter ?? false,
            'isolation_required' => $this->isolation_required ?? false,
            'insurance_verification_status' => $this->insurance_verification_status ?? 'not_verified',
            'payment_status' => $this->payment_status ?? 'not_billed',
            'status' => $this->status ?? 'active',
            'current_phase' => $this->current_phase ?? 'registration',
            'chief_complaints' => is_string($this->chief_complaints) 
                ? json_decode($this->chief_complaints, true) 
                : $this->chief_complaints,
            'symptoms_on_arrival' => is_string($this->symptoms_on_arrival) 
                ? json_decode($this->symptoms_on_arrival, true) 
                : $this->symptoms_on_arrival,
            'vital_signs_summary' => is_string($this->vital_signs_summary) 
                ? json_decode($this->vital_signs_summary, true) 
                : $this->vital_signs_summary,
            'diagnosis_codes' => is_string($this->diagnosis_codes) 
                ? json_decode($this->diagnosis_codes, true) 
                : $this->diagnosis_codes,
            'procedure_codes' => is_string($this->procedure_codes) 
                ? json_decode($this->procedure_codes, true) 
                : $this->procedure_codes,
            'medications_administered' => is_string($this->medications_administered) 
                ? json_decode($this->medications_administered, true) 
                : $this->medications_administered,
            'metadata' => is_string($this->metadata) 
                ? json_decode($this->metadata, true) 
                : $this->metadata,
        ]);
    }
}