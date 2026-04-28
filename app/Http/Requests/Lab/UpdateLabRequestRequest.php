<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateLabRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'visit_id' => 'sometimes|required|exists:visits,id',
            'patient_id' => 'sometimes|required|exists:patients,id',
            'facility_id' => 'sometimes|required|exists:facilities,id',
            'requested_by_staff_id' => 'nullable|exists:staff,id',
            'priority' => 'sometimes|required|in:routine,urgent,stat',
            'status' => 'sometimes|required|in:pending,in_progress,completed,reviewed,cancelled',
            'clinical_notes' => 'nullable|string',
            'diagnosis_context' => 'nullable|array',
            'requested_at' => 'nullable|date',
            'collected_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
            'reviewed_by_staff_id' => 'nullable|exists:staff,id',
            'cancellation_reason' => 'nullable|string',
            'cancelled_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The selected visit does not exist',
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'The selected patient does not exist',
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Invalid priority. Must be routine, urgent, or stat',
            'status.in' => 'Invalid status',
            'diagnosis_context.array' => 'Diagnosis context must be an array',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        throw new HttpResponseException($response);
    }
}