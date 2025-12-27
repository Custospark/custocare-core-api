<?php

namespace App\Http\Requests\VisitActor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreVisitActorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Check if user can create visit actors
        // This should be integrated with your authorization system
        return $this->user() && $this->user()->can('create', \App\Models\VisitActor::class);
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
            'staff_id' => 'required|integer|exists:staff,id',
            'role_at_time' => 'required|string|max:100',
            'credential_snapshot_id' => 'nullable|integer|exists:staff_credentials,id',
            'participation_type' => [
                'required',
                'in:primary_provider,consulting_provider,assisting_provider,supervising_provider,nurse_primary,nurse_assisting,triage_nurse,anesthesiologist,surgical_assistant,pharmacist,technician,therapist,documenting_staff,administrative,observer_trainee'
            ],
            'participation_started_at' => 'required|date',
            'participation_ended_at' => 'nullable|date|after:participation_started_at',
            'time_involvement_minutes' => 'nullable|integer|min:0|max:1440',
            'department_id_at_time' => 'nullable|integer',
            'services_performed' => 'nullable|array',
            'services_performed.*' => 'string|max:20',
            'procedures_assisted' => 'nullable|array',
            'procedures_assisted.*' => 'string|max:100',
            'is_billable_provider' => 'boolean',
            'provider_charge_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'is_teaching_case' => 'boolean',
            'supervising_staff_id' => 'nullable|integer|exists:staff,id',
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
            'facility_id.required' => 'Facility ID is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'visit_id.required' => 'Visit ID is required.',
            'visit_id.exists' => 'The selected visit does not exist.',
            'staff_id.required' => 'Staff ID is required.',
            'staff_id.exists' => 'The selected staff member does not exist.',
            'role_at_time.required' => 'Role at time of participation is required.',
            'participation_type.required' => 'Participation type is required.',
            'participation_type.in' => 'Invalid participation type selected.',
            'participation_started_at.required' => 'Participation start time is required.',
            'participation_started_at.date' => 'Invalid start time format.',
            'participation_ended_at.after' => 'End time must be after start time.',
            'services_performed.array' => 'Services performed must be an array.',
            'procedures_assisted.array' => 'Procedures assisted must be an array.',
            'provider_charge_amount.numeric' => 'Provider charge amount must be a number.',
            'provider_charge_amount.min' => 'Provider charge amount cannot be negative.',
            'supervising_staff_id.exists' => 'The selected supervising staff does not exist.',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create visit actor records.',
                'errors' => ['authorization' => 'Unauthorized action']
            ], JsonResponse::HTTP_FORBIDDEN)
        );
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
                'errors' => $validator->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}