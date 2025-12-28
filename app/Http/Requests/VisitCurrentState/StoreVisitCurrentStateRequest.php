<?php

namespace App\Http\Requests\VisitCurrentState;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\VisitCurrentState;

class StoreVisitCurrentStateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // In production, implement proper authorization logic
        // Example: return $this->user()->can('create', VisitCurrentState::class);
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
            'visit_id' => 'required|integer|exists:visits,id|unique:visit_current_states,visit_id',
            'facility_id' => 'required|integer|exists:facilities,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'current_department_id' => 'nullable|integer|exists:departments,id',
            'current_phase' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(VisitCurrentState::PHASES))
            ],
            'waiting_since' => 'nullable|date',
            'total_wait_minutes' => 'nullable|integer|min:0|max:1440', // Max 24 hours
            'current_phase_duration_minutes' => 'nullable|integer|min:0|max:1440',
            'next_scheduled_action_at' => 'nullable|date|after_or_equal:now',
            'next_action_type' => 'nullable|string|max:100',
            'pending_tasks' => 'nullable|array',
            'pending_tasks.*' => 'string',
            'pending_tasks_count' => 'nullable|integer|min:0|max:255',
            'critical_alerts' => 'nullable|array',
            'has_critical_alerts' => 'boolean',
            'acuity_score' => 'required|integer|min:1|max:5',
            'staff_assigned_ids' => 'nullable|array',
            'staff_assigned_ids.*' => 'integer|exists:staff,id',
            'primary_provider_staff_id' => 'nullable|integer|exists:staff,id',
            'primary_nurse_staff_id' => 'nullable|integer|exists:staff,id',
            'recent_vitals_last_reading' => 'nullable|array',
            'vitals_last_recorded_at' => 'nullable|date',
            'active_orders' => 'nullable|array',
            'active_orders_count' => 'nullable|integer|min:0|max:255',
            'estimated_completion_time' => 'nullable|date|after_or_equal:now',
            'estimated_minutes_remaining' => 'nullable|integer|min:0|max:10080', // Max 7 days
            'last_event_at' => 'nullable|date',
            'last_event_id' => 'nullable|integer',
            'materialized_at' => 'nullable|date',
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
            'visit_id.required' => 'The visit ID is required.',
            'visit_id.unique' => 'A current state already exists for this visit.',
            'current_phase.in' => 'The selected phase is invalid. Valid phases are: ' . 
                implode(', ', array_keys(VisitCurrentState::PHASES)),
            'acuity_score.required' => 'Acuity score is required to prioritize patient care.',
            'acuity_score.min' => 'Acuity score must be at least 1 (lowest acuity).',
            'acuity_score.max' => 'Acuity score must not exceed 5 (highest acuity).',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'visit_id' => 'visit',
            'facility_id' => 'facility',
            'patient_id' => 'patient',
            'current_department_id' => 'current department',
            'current_phase' => 'current phase',
            'acuity_score' => 'acuity score',
            'primary_provider_staff_id' => 'primary provider',
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
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'status' => 422
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to perform this action.',
            'status' => 403
        ], 403);

        throw new HttpResponseException($response);
    }
}