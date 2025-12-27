<?php

namespace App\Http\Requests\VisitRoute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class UpdateVisitRouteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $visitRoute = $this->route('visit_route');
        return $this->user()->can('update', $visitRoute);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'sometimes|integer|exists:facilities,id',
            'visit_id' => 'sometimes|integer|exists:visits,id',
            'from_department_id' => 'nullable|integer|exists:departments,id',
            'to_department_id' => 'sometimes|integer|exists:departments,id',
            'routing_reason' => 'sometimes|in:initial_assignment,specialist_consultation,diagnostic_imaging,laboratory_tests,surgical_procedure,capacity_management,escalation_of_care,de_escalation_of_care,patient_request,admission_to_inpatient,discharge_processing',
            'routing_notes' => 'nullable|string|max:2000',
            'routing_staff_id' => 'nullable|integer|exists:users,id',
            'queue_position_at_move' => 'nullable|integer|min:1',
            'estimated_wait_minutes' => 'nullable|integer|min:0',
            'actual_wait_minutes' => 'nullable|integer|min:0',
            'routed_at' => 'sometimes|date',
            'arrived_at_department' => 'nullable|date|after_or_equal:routed_at',
            'departed_department' => 'nullable|date|after_or_equal:arrived_at_department',
            'actual_transfer_duration_minutes' => 'nullable|integer|min:0',
            'handoff_summary' => 'nullable|string|max:2000',
            'sending_staff_id' => 'nullable|integer|exists:users,id',
            'receiving_staff_id' => 'nullable|integer|exists:users,id',
            'handoff_acknowledged' => 'boolean',
            'handoff_acknowledged_at' => 'nullable|date|required_if:handoff_acknowledged,true',
            'transport_method' => 'nullable|in:ambulatory,wheelchair,stretcher,bed,ambulance',
            'requires_escort' => 'boolean',
            'metadata' => 'nullable|array',
            'force_update' => 'boolean' // Allow updates to completed routes
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'facility_id.exists' => 'The selected facility does not exist.',
            'visit_id.exists' => 'The selected visit does not exist.',
            'to_department_id.exists' => 'The selected destination department does not exist.',
            'routing_reason.in' => 'The selected routing reason is invalid.',
            'routed_at.date' => 'The routing timestamp must be a valid date.',
            'arrived_at_department.after_or_equal' => 'Arrival time must be after or equal to routing time.',
            'departed_department.after_or_equal' => 'Departure time must be after or equal to arrival time.',
            'handoff_acknowledged_at.required_if' => 'Handoff acknowledgment timestamp is required when handoff is acknowledged.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'facility_id' => 'facility',
            'visit_id' => 'visit',
            'from_department_id' => 'source department',
            'to_department_id' => 'destination department',
            'routing_reason' => 'routing reason',
            'routing_notes' => 'routing notes',
            'routing_staff_id' => 'routing staff',
            'queue_position_at_move' => 'queue position',
            'estimated_wait_minutes' => 'estimated wait minutes',
            'actual_wait_minutes' => 'actual wait minutes',
            'routed_at' => 'routing time',
            'arrived_at_department' => 'arrival time',
            'departed_department' => 'departure time',
            'actual_transfer_duration_minutes' => 'transfer duration',
            'handoff_summary' => 'handoff summary',
            'sending_staff_id' => 'sending staff',
            'receiving_staff_id' => 'receiving staff',
            'handoff_acknowledged' => 'handoff acknowledged',
            'handoff_acknowledged_at' => 'handoff acknowledgment time',
            'transport_method' => 'transport method',
            'requires_escort' => 'requires escort',
            'metadata' => 'metadata',
            'force_update' => 'force update'
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
            'data' => null
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this visit route.',
            'errors' => ['authorization' => 'Insufficient permissions.'],
            'data' => null
        ], 403);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean fields are properly cast
        $this->merge([
            'handoff_acknowledged' => filter_var($this->handoff_acknowledged, FILTER_VALIDATE_BOOLEAN),
            'requires_escort' => filter_var($this->requires_escort, FILTER_VALIDATE_BOOLEAN),
            'force_update' => filter_var($this->force_update, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Additional validation with database.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('from_department_id') && $this->has('to_department_id')) {
                if ($this->from_department_id == $this->to_department_id) {
                    $validator->errors()->add(
                        'to_department_id',
                        'Destination department must be different from source department.'
                    );
                }
            }
            
            // Prevent setting departure without arrival
            if ($this->departed_department && !$this->arrived_at_department) {
                $validator->errors()->add(
                    'departed_department',
                    'Cannot set departure time without arrival time.'
                );
            }
            
            // Ensure routed_at is not in the future (unless explicitly allowed)
            if ($this->routed_at && strtotime($this->routed_at) > time()) {
                $validator->errors()->add(
                    'routed_at',
                    'Routing time cannot be in the future.'
                );
            }
        });
    }
}