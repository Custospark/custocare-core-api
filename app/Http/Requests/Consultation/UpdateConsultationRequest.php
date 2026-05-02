<?php

declare(strict_types=1);

namespace App\Http\Requests\Consultation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateConsultationRequest extends FormRequest
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
            'specialty_required' => 'nullable|string|max:200',
            'consultation_type' => 'nullable|in:in_person,telemedicine,urgent,elective,emergency',
            'priority' => 'nullable|in:routine,urgent,emergent',
            'clinical_question' => 'nullable|string',
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
            'consultation_type.in' => 'Invalid consultation type',
            'priority.in' => 'Invalid priority',
            'duration_minutes.min' => 'Duration must be at least 5 minutes',
            'duration_minutes.max' => 'Duration cannot exceed 480 minutes',
            'followup_by.after' => 'Follow-up date must be after scheduled date',
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