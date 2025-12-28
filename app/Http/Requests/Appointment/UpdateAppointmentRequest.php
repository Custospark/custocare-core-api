<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use App\Models\Appointment;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * The appointment instance.
     */
    protected ?Appointment $appointment = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Get the appointment from route parameter
        $this->appointment = $this->route('appointment');
        
        // Authorization is handled by policies
        return $this->user() !== null && $this->appointment !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'facility_id' => 'sometimes|integer|exists:facilities,id',
            'patient_id' => 'sometimes|integer|exists:patients,id',
            'provider_staff_id' => 'sometimes|integer|exists:staff,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'appointment_type' => 'sometimes|in:' . implode(',', [
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
            'scheduled_start_time' => 'sometimes|date|after:now',
            'duration_minutes' => 'sometimes|integer|min:5|max:480',
            'reason_for_visit' => 'nullable|string|max:1000',
            'requested_services' => 'nullable|array',
            'requested_services.*' => 'string|max:255',
            'status' => 'sometimes|in:' . implode(',', [
                'scheduled',
                'confirmed',
                'checked_in',
                'in_progress',
                'completed',
                'no_show',
                'cancelled',
                'rescheduled'
            ]),
            'cancellation_reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ];

        // If updating status to cancelled, require cancellation reason
        if ($this->input('status') === 'cancelled') {
            $rules['cancellation_reason'] = 'required|string|max:500';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facility_id.exists' => 'The selected facility does not exist',
            'patient_id.exists' => 'The selected patient does not exist',
            'provider_staff_id.exists' => 'The selected provider does not exist',
            'appointment_type.in' => 'The selected appointment type is invalid',
            'scheduled_start_time.after' => 'Appointment time must be in the future',
            'duration_minutes.min' => 'Appointment duration must be at least 5 minutes',
            'duration_minutes.max' => 'Appointment duration cannot exceed 8 hours',
            'reason_for_visit.max' => 'Reason for visit cannot exceed 1000 characters',
            'status.in' => 'The selected status is invalid',
            'cancellation_reason.required' => 'Cancellation reason is required when cancelling an appointment',
            'cancellation_reason.max' => 'Cancellation reason cannot exceed 500 characters',
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
            'status' => 'status',
            'cancellation_reason' => 'cancellation reason',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Check if appointment can be updated
            if ($this->appointment && 
                ($this->appointment->isCompleted() || 
                 $this->appointment->status === Appointment::STATUS_CANCELLED)) {
                $validator->errors()->add(
                    'appointment',
                    'Cannot update a completed or cancelled appointment'
                );
            }

            // Validate status transitions
            if ($this->has('status')) {
                $this->validateStatusTransition($validator);
            }
        });
    }

    /**
     * Validate appointment status transition.
     */
    private function validateStatusTransition(Validator $validator): void
    {
        if (!$this->appointment) {
            return;
        }

        $currentStatus = $this->appointment->status;
        $newStatus = $this->input('status');

        $allowedTransitions = [
            'scheduled' => ['confirmed', 'cancelled', 'rescheduled'],
            'confirmed' => ['checked_in', 'cancelled', 'no_show'],
            'checked_in' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
            'no_show' => [],
            'rescheduled' => ['scheduled'],
        ];

        if (isset($allowedTransitions[$currentStatus]) && 
            !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            $validator->errors()->add(
                'status',
                "Cannot change status from {$currentStatus} to {$newStatus}"
            );
        }
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
                'message' => 'You are not authorized to update this appointment',
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