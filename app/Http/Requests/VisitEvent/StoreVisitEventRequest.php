<?php

namespace App\Http\Requests\VisitEvent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreVisitEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by VisitEventPolicy
        return $this->user()->can('create', \App\Models\VisitEvent::class);
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
            'visit_id' => 'required|integer|exists:visits,id',
            'event_type' => [
                'required',
                'string',
                'in:visit_created,patient_arrived,patient_registered,triage_started,triage_completed,' .
                'vitals_recorded,routed_to_department,provider_assigned,consultation_started,' .
                'consultation_completed,diagnostic_ordered,diagnostic_completed,medication_ordered,' .
                'medication_administered,procedure_started,procedure_completed,condition_changed,' .
                'admission_ordered,transfer_initiated,discharge_ordered,discharge_completed,' .
                'visit_cancelled,patient_left_ama,patient_lwbs,clinical_note_added,billing_updated,' .
                'insurance_verified,consent_obtained,alert_triggered,escalation_required'
            ],
            'event_payload' => 'required|array',
            'event_payload.schema_version' => 'required|string|max:20',
            'payload_schema_version' => 'sometimes|string|max:20',
            'actor_type' => 'required|string|in:staff,patient,system,device,external_system',
            'actor_id' => 'nullable|integer',
            'actor_identifier' => 'nullable|string|max:200',
            'department_id_at_time' => 'nullable|integer',
            'system_component' => 'nullable|string|max:100',
            'client_ip' => 'nullable|ip',
            'client_user_agent' => 'nullable|string|max:512',
            'event_occurred_at' => 'required|date|before_or_equal:now',
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
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The selected visit does not exist',
            'event_type.required' => 'Event type is required',
            'event_type.in' => 'The selected event type is invalid',
            'event_payload.required' => 'Event payload is required',
            'event_payload.array' => 'Event payload must be a valid JSON object',
            'event_payload.schema_version.required' => 'Payload schema version is required',
            'actor_type.required' => 'Actor type is required',
            'actor_type.in' => 'The selected actor type is invalid',
            'event_occurred_at.required' => 'Event occurred timestamp is required',
            'event_occurred_at.before_or_equal' => 'Event occurred timestamp cannot be in the future',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
            'status_code' => 422,
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to create visit events',
            'status_code' => 403,
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
        // Ensure event_payload is an array if it's JSON string
        if ($this->has('event_payload') && is_string($this->event_payload)) {
            $this->merge([
                'event_payload' => json_decode($this->event_payload, true) ?: [],
            ]);
        }

        // Ensure metadata is an array if it's JSON string
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }

        // Set default payload schema version if not provided
        if (!$this->has('payload_schema_version') && $this->has('event_payload.schema_version')) {
            $this->merge([
                'payload_schema_version' => $this->input('event_payload.schema_version', '1.0'),
            ]);
        }
    }
}