<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreConsultationRequest extends FormRequest
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
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'requesting_staff_id' => 'nullable|exists:staff,id',
            'specialty_required' => 'required|string|max:200',
            'consultation_type' => 'nullable|in:in_person,telemedicine,urgent,elective,emergency',
            'priority' => 'nullable|in:routine,urgent,emergent',
            'clinical_question' => 'required|string',
            'background_information' => 'nullable|string',
            'attached_documents' => 'nullable|array',
            'scheduled_for' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'location' => 'nullable|string|max:200',
            'requires_followup' => 'nullable|boolean',
            'followup_by' => 'nullable|date|after:scheduled_for',
            'followup_instructions' => 'nullable|string',
            'custom_fields' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The selected visit does not exist',
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'The selected patient does not exist',
            'specialty_required.required' => 'Specialty required is required',
            'clinical_question.required' => 'Clinical question is required',
            'consultation_type.in' => 'Invalid consultation type',
            'priority.in' => 'Invalid priority',
            'duration_minutes.min' => 'Duration must be at least 5 minutes',
            'duration_minutes.max' => 'Duration cannot exceed 480 minutes',
            'followup_by.after' => 'Follow-up date must be after scheduled date',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'routine']);
        }

        // Set default consultation_type if not provided
        if (!$this->has('consultation_type')) {
            $this->merge(['consultation_type' => 'in_person']);
        }
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