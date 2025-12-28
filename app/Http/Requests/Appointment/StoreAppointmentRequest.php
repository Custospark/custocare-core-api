<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'provider_staff_id' => 'required|integer|exists:staff,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'appointment_type' => 'required|in:' . implode(',', [
                'new_patient_consultation',
                'followup_visit',
                'annual_physical',
                'procedure',
                'diagnostic_test',
                'therapy_session',
                'telehealth',
                'vaccination',
                'consultation'
            ]),
            'scheduled_start_time' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'reason_for_visit' => 'nullable|string|max:1000',
            'requested_services' => 'nullable|array',
            'requested_services.*' => 'string|max:255',
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
            'facility_id.required' => 'Please select a facility',
            'facility_id.exists' => 'The selected facility does not exist',
            'patient_id.required' => 'Please select a patient',
            'patient_id.exists' => 'The selected patient does not exist',
            'provider_staff_id.required' => 'Please select a healthcare provider',
            'provider_staff_id.exists' => 'The selected provider does not exist',
            'appointment_type.required' => 'Please select an appointment type',
            'appointment_type.in' => 'The selected appointment type is invalid',
            'scheduled_start_time.required' => 'Please select a date and time for the appointment',
            'scheduled_start_time.after' => 'Appointment time must be in the future',
            'duration_minutes.required' => 'Please specify the appointment duration',
            'duration_minutes.min' => 'Appointment duration must be at least 5 minutes',
            'duration_minutes.max' => 'Appointment duration cannot exceed 8 hours',
            'reason_for_visit.max' => 'Reason for visit cannot exceed 1000 characters',
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
            'facility_id' => 'facility',
            'patient_id' => 'patient',
            'provider_staff_id' => 'healthcare provider',
            'department_id' => 'department',
            'appointment_type' => 'appointment type',
            'scheduled_start_time' => 'scheduled time',
            'duration_minutes' => 'duration',
            'reason_for_visit' => 'reason for visit',
            'requested_services' => 'requested services',
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
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
                'data' => null
            ], 422)
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
                'message' => 'You are not authorized to create appointments',
                'errors' => [],
                'data' => null
            ], 403)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure requested_services is always an array
        if ($this->has('requested_services') && is_string($this->requested_services)) {
            $this->merge([
                'requested_services' => json_decode($this->requested_services, true) ?? [],
            ]);
        }

        // Ensure metadata is always an array
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true) ?? [],
            ]);
        }
    }
}